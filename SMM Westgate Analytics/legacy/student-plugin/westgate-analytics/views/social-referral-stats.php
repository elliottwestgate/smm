<?php 
//social referral statistics
function pa_include_social_referral( $current, $social_referral_stats ) {
	if ( ! empty( $social_referral_stats["rows"] ) ) {
		$total = 0; //create temp variable
		foreach ( $social_referral_stats["rows"] as $s_stats ) {
			if ( $s_stats[0] === 'Social' ) {
				$total = $s_stats[1];
			}
		}
		echo '<td class="social_referral_stats">' . $total . '</td>';
	} else {
		echo '<td class="social_referral_stats">0</td>';
	}
} 
?>