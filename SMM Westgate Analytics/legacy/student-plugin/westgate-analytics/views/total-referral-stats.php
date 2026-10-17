<?php
//total referral statistics
function pa_include_total_referral( $current, $total_referral_stats ) {
	if (! empty( $total_referral_stats["rows"] ) ) { 
		echo "<br/>";
		echo "Pageview Sources:" . ' ';
		echo "<br/>";
		foreach ($total_referral_stats["rows"] as $t_stats){
			echo $t_stats[0] . ' ';
			echo $t_stats[1] . ' ';
			echo $t_stats[2] . ' ';
			echo "<br/>";
		}
	}
} ?>