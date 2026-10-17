<?php
//new users statistics
function pa_include_new_users( $current, $new_users_stats ) {
	if ( ! empty( $new_users_stats["rows"] ) ) { 
		$total = 0; //create temp variable
		foreach ( $new_users_stats["rows"] as $n_stats ){
			if ( $n_stats[0] === 'New Visitor' ) {
				$total += $n_stats[1];
			}
		}
		echo '<td class="new_users_stats">' . $total . '</td>';
	} else {
		echo '<td class="new_users_stats">0</td>';
	}
} 
?>