<?php
/**
 * Analytics hooks: GA4 and/or Matomo.
 *
 * Both are optional and both are skipped for logged-in editors, so the team's
 * own content checks never show up as traffic in the reports.
 *
 * @package BusinessCMS
 */

defined( 'ABSPATH' ) || exit;

class BCMS_Analytics {

	public function __construct() {
		add_action( 'wp_head', array( $this, 'head' ), 20 );
		add_action( 'bcms_enquiry_received', array( $this, 'flag_conversion' ), 10, 2 );
	}

	private function should_track(): bool {
		if ( is_admin() || wp_doing_ajax() ) {
			return false;
		}
		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			return false;
		}
		if ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) {
			return false;
		}
		/**
		 * Filter whether analytics should run for this request.
		 *
		 * @param bool $track
		 */
		return (bool) apply_filters( 'bcms_should_track', true );
	}

	public function head(): void {
		if ( ! $this->should_track() ) {
			return;
		}

		$ga4 = (string) BCMS_Settings::get( 'ga4_id', '' );
		if ( '' !== $ga4 && preg_match( '/^G-[A-Z0-9]{6,}$/i', $ga4 ) ) {
			$this->ga4( $ga4 );
		}

		$matomo_url = (string) BCMS_Settings::get( 'matomo_url', '' );
		$matomo_id  = (string) BCMS_Settings::get( 'matomo_site_id', '' );
		if ( '' !== $matomo_url && '' !== $matomo_id ) {
			$this->matomo( $matomo_url, $matomo_id );
		}
	}

	private function ga4( string $id ): void {
		?>
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( rawurlencode( $id ) ); ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', <?php echo wp_json_encode( $id ); ?>, { anonymize_ip: true });
</script>
		<?php
	}

	private function matomo( string $url, string $site_id ): void {
		$url = trailingslashit( $url );
		?>
<!-- Matomo -->
<script>
var _paq = window._paq = window._paq || [];
_paq.push(['trackPageView']);
_paq.push(['enableLinkTracking']);
(function() {
	var u = <?php echo wp_json_encode( $url ); ?>;
	_paq.push(['setTrackerUrl', u + 'matomo.php']);
	_paq.push(['setSiteId', <?php echo wp_json_encode( $site_id ); ?>]);
	var d = document, g = d.createElement('script'), s = d.getElementsByTagName('script')[0];
	g.async = true; g.src = u + 'matomo.js'; s.parentNode.insertBefore(g, s);
})();
</script>
		<?php
	}

	/**
	 * The form posts over REST, so the conversion event is queued for the next
	 * page view rather than fired from a request analytics never sees.
	 *
	 * @param int                 $post_id
	 * @param array<string,mixed> $data
	 */
	public function flag_conversion( int $post_id, array $data ): void {
		do_action( 'bcms_analytics_conversion', $post_id, $data );
	}
}
