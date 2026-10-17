<?php
//organic referral statistics
function pa_include_organic_referral( $current, $organic_referral_stats ) {
	if ( ! empty( $organic_referral_stats["rows"] ) ) { 
		foreach ( $organic_referral_stats["rows"] as $o_stats ) {
			if ( $o_stats[0] === 'Organic Search' ) {
				$total = $o_stats[1];
			}
		}
		echo '<td class="organic_referral_stats">' . $total . '</td>';
	} else {
		echo '<td class="organic_referral_stats">0</td>';
	}
} 
?>