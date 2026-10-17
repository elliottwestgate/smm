<?php
//referral referral statistics
function pa_include_referral_referral( $current, $referral_referral_stats ) {
	if ( ! empty( $referral_referral_stats["rows"] ) ) { 
		foreach ( $referral_referral_stats["rows"] as $r_stats ){
			if ( $r_stats[0] === 'Referral' ) {
				$total = $r_stats[1];
			}
		}
		echo '<td class="referral_referral_stats">' . $total . '</td>';
	} else {
		echo '<td class="referral_referral_stats">0</td>';
	}
} 
?>