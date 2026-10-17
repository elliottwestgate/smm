<?php
//direct referral statistics
function pa_include_direct_referral( $current, $direct_referral_stats ) {
	if ( ! empty( $direct_referral_stats["rows"] ) ) { 
		foreach ( $direct_referral_stats["rows"] as $d_stats ) {
			if ( $d_stats[0] === 'Direct' ) {
				$total = $d_stats[1];
			}
		}
		echo '<td class="direct_referral_stats">' . $total . '</td>';
	} else {
		echo '<td class="direct_referral_stats">0</td>';
	}
} 
?>