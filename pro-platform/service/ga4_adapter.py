"""Read-only GA4 adapter; transport and authorized company config are injected.
Transport signature: transport(path, JSON_body) -> decoded Google response.
No tokens, arbitrary URLs, or caller-selected property IDs enter this module.
"""
from datetime import date, datetime, timedelta, timezone
from zoneinfo import ZoneInfo
import math
import re

class ReportingError(Exception):
    pass

class GA4Adapter:
    def __init__(self, transport, config):
        self.transport=transport
        self.config=dict(config)
        prop=str(config.get('property',''))
        if not re.fullmatch(r'[1-9][0-9]*',prop):
            raise ValueError('A numeric GA4 property is required')
        self.path='/v1beta/properties/'+prop+':runReport'
        ZoneInfo(config['timezone'])
        self.goals={}
        for kind in ('form_events','phone_events'):
            names=config.get(kind)
            if not isinstance(names,list) or not names or any(not isinstance(n,str) or not re.fullmatch(r'[A-Za-z][A-Za-z0-9_]{0,39}',n) for n in names):
                raise ValueError('Configure explicit form and phone event names')
            self.goals[kind]=set(names)
        if self.goals['form_events'] & self.goals['phone_events']:
            raise ValueError('Goal definitions must not overlap')

    @staticmethod
    def number(value):
        try:
            result=float(value)
        except (TypeError,ValueError):
            raise ReportingError('Malformed GA4 metric') from None
        if not math.isfinite(result) or result<0:
            raise ReportingError('Malformed GA4 metric')
        return result

    def report(self,start,end,dimensions,metrics):
        request={'dateRanges':[{'startDate':start,'endDate':end}],
                 'dimensions':[{'name':d} for d in dimensions],
                 'metrics':[{'name':m} for m in metrics],
                 'limit':10000,'offset':0,'returnPropertyQuota':True}
        if dimensions:
            request['orderBys']=[{'dimension':{'dimensionName':d}} for d in dimensions]
        rows=[]; notes=[]; constrained=False
        while True:
            response=self.transport(self.path,request.copy())
            if not isinstance(response,dict) or 'error' in response:
                raise ReportingError('Google could not return this report')
            if [r['name'] for r in response.get('dimensionHeaders',[])]!=dimensions or [r['name'] for r in response.get('metricHeaders',[])]!=metrics:
                raise ReportingError('GA4 report headers do not match the request')
            metadata=response.get('metadata',{})
            if metadata.get('timeZone') and metadata['timeZone']!=self.config['timezone']:
                raise ReportingError('Property timezone differs from the connection')
            if metadata.get('subjectToThresholding'):
                constrained=True;notes.append('GA4 reports privacy thresholding may apply.')
            if metadata.get('dataLossFromOtherRow'):
                constrained=True;notes.append('GA4 reports data consolidated into an other row.')
            if metadata.get('samplingMetadatas'):
                constrained=True;notes.append('GA4 reports sampled data.')
            batch=response.get('rows',[])
            for row in batch:
                ds=row.get('dimensionValues',[]);ms=row.get('metricValues',[])
                if len(ds)!=len(dimensions) or len(ms)!=len(metrics):
                    raise ReportingError('Malformed GA4 row')
                rows.append({**{d:v['value'] for d,v in zip(dimensions,ds)},
                             **{m:self.number(v['value']) for m,v in zip(metrics,ms)}})
            count=int(response.get('rowCount',len(rows)))
            if len(rows)>=count:break
            if not batch or len(rows)>100000:
                raise ReportingError('GA4 pagination did not complete')
            request['offset']=len(rows)
        return rows,list(dict.fromkeys(notes)),constrained

    def snapshot(self,start,end,now=None):
        first,last=date.fromisoformat(start),date.fromisoformat(end)
        clock=now or datetime.now(timezone.utc)
        if clock.tzinfo is None:raise ValueError('Timezone-aware clock required')
        yesterday=clock.astimezone(ZoneInfo(self.config['timezone'])).date()-timedelta(days=1)
        if first>last or (last-first).days>1095 or last>yesterday:
            raise ValueError('Choose completed reporting days, ending yesterday or earlier')
        notes=[];data={}
        def query(dims,metrics):
            rows,warnings,limited=self.report(start,end,dims,metrics)
            notes.extend(warnings)
            return rows,limited
        totals,limited=query([],['sessions','totalUsers','newUsers'])
        if len(totals)>1:raise ReportingError('Unexpected totals rows')
        total=totals[0] if totals else {}
        data.update(sessions=total.get('sessions',None if limited else 0),
                    users=total.get('totalUsers',None if limited else 0),
                    new_users=total.get('newUsers',None if limited else 0))
        returning,limited=query(['newVsReturning'],['totalUsers'])
        data['returning_users']=next((r['totalUsers'] for r in returning if r['newVsReturning']=='returning'),None if limited else 0)
        events,limited=query(['eventName'],['eventCount'])
        for kind,names in self.goals.items():
            matched=[r['eventCount'] for r in events if r['eventName'] in names]
            # Partial or privacy-affected reports do not imply absent event names are zero.
            data[kind]=sum(matched) if matched else (None if limited else 0)
        source_rows,_=query(['sessionSourceMedium','sessionDefaultChannelGroup'],['sessions'])
        sources=[{'source_medium':r['sessionSourceMedium'],'channel':r['sessionDefaultChannelGroup'],'sessions':r['sessions']} for r in source_rows]
        profile={}
        for label,dimension in [('age','userAgeBracket'),('gender','userGender'),('country','country'),('city','city'),('device','deviceCategory')]:
            try:
                rows,_=query([dimension],['sessions'])
                if len(rows)>1000:raise ReportingError('Audience row limit exceeded')
                profile[label]=[{'label':r[dimension],'sessions':r['sessions']} for r in rows]
            except ReportingError:
                profile[label]=None
                notes.append('Audience '+label+' report unavailable.')
        notes.extend(['Raw configured goal events may repeat per visitor or session; they are not verified sales or calls.',
                      'Returning users are website visitors, not verified returning customers.',
                      'Data through yesterday may still be revised by Google.'])
        return {'schema':'wgpp-snapshots-v1','snapshots':[{
            'property':str(self.config['property']),'company':self.config['company'],
            'website':self.config['website'],'timezone':self.config['timezone'],
            'from':start,'to':end,'collected_at':clock.isoformat(),
            'metrics':data,'sources':sources,'profile':profile,'notes':list(dict.fromkeys(notes))}]}
