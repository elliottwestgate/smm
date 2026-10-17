<?php
//goal completion statistics
function pa_include_goal_completion( $current, $goal_completion_stats ) {
	if ( ! empty( $goal_completion_stats["rows"] ) ) { 
		foreach ( $goal_completion_stats["rows"] as $g_stats ) {
			echo '<td class="goal_completion_stats">' . $g_stats[0] . '</td>';
		}
	} else {
		echo '<td class="goal_completion_stats">0</td>';
	}
} 
?>