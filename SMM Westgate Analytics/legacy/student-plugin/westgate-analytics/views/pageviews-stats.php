<?php 
//pageviews statistics
function pa_include_pageviews( $current, $pageviews_stats ) {
	if ( ! empty( $pageviews_stats["rows"] ) ) { 
		foreach ( $pageviews_stats["rows"] as $p_stats ) {
			echo '<td class="pageviews_stats">' . $p_stats[0] . '</td>';
		}
	} else {
		echo '<td class="pageviews_stats">0</td>';
	}
} 
?>
