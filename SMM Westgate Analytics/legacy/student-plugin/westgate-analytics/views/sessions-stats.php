<?php 
//sessions statistics
function pa_include_sessions( $current, $sessions_stats ) {
	if ( ! empty( $sessions_stats["rows"] ) ) {
		foreach ( $sessions_stats["rows"] as $s_stats ) {
			echo '<td class="sessions_stats">' . $s_stats[0] . '</td>';
		}
	} else {
		echo '<td class="sessions_stats">0</td>';
	}
} 
?>