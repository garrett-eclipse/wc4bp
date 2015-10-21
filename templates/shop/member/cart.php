<?php
/**
 * @package		WordPress
 * @subpackage	BuddyPress, Woocommerce
 * @author		Boris Glumpler
 * @copyright	2011, Themekraft
 * @link		https://github.com/Themekraft/BP-Shop-Integration
 * @license		http://www.opensource.org/licenses/gpl-2.0.php GPL License
 */
?>
<div id="item-body" role="main">
	<?php do_action( 'wc4bp_before_cart_body' ); ?>

	<?php echo do_shortcode( '[woocommerce_cart]' ); ?>

	<?php do_action( 'wc4bp_after_cart_body' ); ?>

</div><!-- #item-body -->