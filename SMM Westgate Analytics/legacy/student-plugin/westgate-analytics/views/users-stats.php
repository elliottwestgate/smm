<?php 
//users statistics
function pa_include_users( $current, $users_stats ) {
	if ( ! empty( $users_stats["rows"] ) ) {
		foreach ( $users_stats["rows"] as $u_stats ) {
			echo '<td class="users_stats">' . $u_stats[0] . '</td>';
		}
	} else {
		echo '<td class="users_stats">0</td>';
	}
} 
?>