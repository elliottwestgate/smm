from pathlib import Path
root=Path(__file__).parents[1]/'plugin'/'westgate-practicum-pro'
def change(file,pairs):
 p=root/file;s=p.read_text()
 for old,new in pairs:
  assert old in s,(file,old)
  s=s.replace(old,new)
 p.write_text(s)
change('assets/analytics.js',[
 ("const change=(n,baseline)","const reportDate=d=>{const m=/^(\\d{4})-(\\d{2})-(\\d{2})$/.exec(d||'');return m?m[2]+'/'+m[3]+'/'+m[1]:d;};\nconst change=(n,baseline)"),
 ("data.ranges.current.join(' to ')","data.ranges.current.map(reportDate).join(' to ')"),
 ("data.ranges.comparison.join(' to ')","data.ranges.comparison.map(reportDate).join(' to ')"),
 ("Import this range or connect a reporting provider.","Check your connections and select a period with available data."),
 ("Imported snapshot — not automatically refreshed","Saved snapshot — not automatically refreshed"),
 ("'Comparison: '+value(a.prior_goal_rate)","'Previous: '+value(a.prior_goal_rate)"),
 ("'New versus returning • '","'Returning-to-new user ratio • '"),
 ("row(root.querySelector('[data-rate]'),'Goal events per 100 sessions'","root.querySelector('.wgpa-movement h3').textContent='Brand Axis · Campaign Signals';\n row(root.querySelector('[data-rate]'),'Goal events per 100 sessions'"),
 ("const notes=[...s.notes,","const notes=[...s.notes,'Brand Axis: the SMM model of Loyalty and Engagement in environmental and competitive contexts. The diagram uses session change and change in the returning-to-new-user ratio as directional signals; it does not directly measure those qualities or prove their causes.','Message Matrix: target audiences × content buckets × channels. Use content signals to Develop, Reframe or Create a related sequence in the publishing calendar.',"),
 ("Current loop in blue; comparison baseline dashed blue-gray. Competitive benchmarking is not connected.","Brand Axis signals: session change and returning-to-new user ratio. Current loop in blue; previous baseline dashed blue-gray. These inform Brand alignment; they do not directly measure Loyalty or Engagement."),
 ("Analytics ready. Waiting for the remaining report data…","Website analytics ready. Waiting for the remaining Report data…")
])
change('assets/reviews.js',[
 ("01 / BRAND POSITION","01 / BRAND AXIS"),
 ("Your Brand in the Matrix","Brand Axis · Campaign Signals"),
 ("A comparable baseline is needed to place the Brand in the Matrix.","A comparable baseline is needed to show directional Brand Axis signals."),
 ("Goal delivery has no comparable baseline.","Goal-event frequency has no comparable baseline."),
 ("Goal delivery per session is weakening.","Goal-event frequency per session is weakening."),
 ("Goal delivery per session is improving.","Goal-event frequency per session is improving."),
 ("Goal delivery per session is unchanged.","Goal-event frequency per session is unchanged."),
 ("Current movement is relative to your previous results. The diagram does not measure competitors or market share.","Brand Axis is the SMM model of Loyalty and Engagement. These plotted session and returning-to-new-user signals inform that assessment; they do not directly measure those qualities, competitors or market share."),
 ("Audience × content focus × channel. Carry one Brand narrative through a related sequence in the publishing calendar.","Message Matrix: target audiences × content buckets × channels. Use these content signals to Develop, Reframe or Create a related sequence in the publishing calendar."),
 ("el('strong','Content signals')","el('strong','Content Signals')"),
 ("Evidence and measurement notes","Supporting results and measurement notes"),
 ("Select Previous period or Previous year to request a movement review.","Select Prior period or Previous year to request a Pro Analytics Review.")
])
change('reviews.php',[
 ("The Message Matrix is central: connect audience verticals, topic buckets and channel roles to the Brand narrative.","DISTINGUISH THE FRAMEWORKS: Brand Axis is the SMM conceptual model of Loyalty and Engagement in environmental and competitive contexts. The plotted session change and returning-to-new-user ratio are directional signals that inform assessment; they do not directly measure Loyalty, Engagement, sentiment or competitive pressure. Message Matrix is the planning framework connecting target audience verticals, content buckets and channel roles to the Brand narrative. Do not call the Message Matrix the Brand Axis or describe either as a generic Brand Matrix. Use Brand Axis when assessing Brand alignment and Message Matrix when organizing the content response.")
])
change('company.php',[("'0.6.17-preview'","'0.6.18-preview'")])
print('Aligned Report, Review, generation guidance and cache version.')
