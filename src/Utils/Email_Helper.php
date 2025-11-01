<?php
/**
 * Email Helper Class
 *
 * @package LNMC_Member_Hub
 * @since 1.0.0
 */

namespace LNMC_Member_Hub\Utils;

/**
 * Email Helper class for sending membership-related emails.
 */
class Email_Helper {

	/**
	 * Send welcome email to new member.
	 *
	 * @param int    $user_id User ID.
	 * @param string $plan_name Membership plan name.
	 * @return bool
	 */
	public static function send_welcome_email( int $user_id, string $plan_name = '' ): bool {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		$subject = sprintf(
			__( 'Welcome to %s!', 'lnmc-member-hub' ),
			get_bloginfo( 'name' )
		);

		$message = self::get_welcome_email_template( $user, $plan_name );

		return wp_mail( $user->user_email, $subject, $message, self::get_email_headers() );
	}

	/**
	 * Send payment confirmation email.
	 *
	 * @param int    $user_id User ID.
	 * @param float  $amount Payment amount.
	 * @param string $plan_name Membership plan name.
	 * @return bool
	 */
	public static function send_payment_confirmation( int $user_id, float $amount, string $plan_name = '' ): bool {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		$subject = sprintf(
			__( 'Payment Confirmation - %s', 'lnmc-member-hub' ),
			get_bloginfo( 'name' )
		);

		$message = self::get_payment_confirmation_template( $user, $amount, $plan_name );

		return wp_mail( $user->user_email, $subject, $message, self::get_email_headers() );
	}

	/**
	 * Send renewal reminder email.
	 *
	 * @param int    $user_id User ID.
	 * @param string $renewal_date Renewal date.
	 * @return bool
	 */
	public static function send_renewal_reminder( int $user_id, string $renewal_date ): bool {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		$subject = sprintf(
			__( 'Membership Renewal Reminder - %s', 'lnmc-member-hub' ),
			get_bloginfo( 'name' )
		);

		$message = self::get_renewal_reminder_template( $user, $renewal_date );

		return wp_mail( $user->user_email, $subject, $message, self::get_email_headers() );
	}

	/**
	 * Send admin notification for new member.
	 *
	 * @param int    $user_id User ID.
	 * @param string $plan_name Membership plan name.
	 * @return bool
	 */
	public static function send_admin_new_member_notification( int $user_id, string $plan_name = '' ): bool {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		$admin_email = get_option( 'admin_email' );
		$subject = sprintf(
			__( 'New Member Registration - %s', 'lnmc-member-hub' ),
			get_bloginfo( 'name' )
		);

		$message = self::get_admin_new_member_template( $user, $plan_name );

		return wp_mail( $admin_email, $subject, $message, self::get_email_headers() );
	}

	/**
	 * Get welcome email template.
	 *
	 * @param \WP_User $user User object.
	 * @param string   $plan_name Membership plan name.
	 * @return string
	 */
	private static function get_welcome_email_template( \WP_User $user, string $plan_name ): string {
		$dashboard_url = home_url( '/member-dashboard/' );
		$site_name = get_bloginfo( 'name' );

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<title><?php echo esc_html( $site_name ); ?></title>
		</head>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
				<h1 style="color: #0073aa;"><?php echo esc_html( $site_name ); ?></h1>
				
				<h2><?php esc_html_e( 'Welcome to our community!', 'lnmc-member-hub' ); ?></h2>
				
				<p><?php printf( esc_html__( 'Hi %s,', 'lnmc-member-hub' ), esc_html( $user->display_name ) ); ?></p>
				
				<p><?php esc_html_e( 'Thank you for joining our membership community! We\'re excited to have you on board.', 'lnmc-member-hub' ); ?></p>
				
				<?php if ( ! empty( $plan_name ) ) : ?>
					<p><?php printf( esc_html__( 'Your membership plan: %s', 'lnmc-member-hub' ), esc_html( $plan_name ) ); ?></p>
				<?php endif; ?>
				
				<div style="background-color: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 5px;">
					<h3><?php esc_html_e( 'What\'s next?', 'lnmc-member-hub' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'Access your member dashboard', 'lnmc-member-hub' ); ?></li>
						<li><?php esc_html_e( 'Explore exclusive content and resources', 'lnmc-member-hub' ); ?></li>
						<li><?php esc_html_e( 'Connect with other members', 'lnmc-member-hub' ); ?></li>
						<li><?php esc_html_e( 'Stay updated with our latest news and events', 'lnmc-member-hub' ); ?></li>
					</ul>
				</div>
				
				<p>
					<a href="<?php echo esc_url( $dashboard_url ); ?>" style="background-color: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">
						<?php esc_html_e( 'Access Your Dashboard', 'lnmc-member-hub' ); ?>
					</a>
				</p>
				
				<p><?php esc_html_e( 'If you have any questions, please don\'t hesitate to contact us.', 'lnmc-member-hub' ); ?></p>
				
				<p><?php esc_html_e( 'Best regards,', 'lnmc-member-hub' ); ?><br>
				<?php echo esc_html( $site_name ); ?> Team</p>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get payment confirmation email template.
	 *
	 * @param \WP_User $user User object.
	 * @param float    $amount Payment amount.
	 * @param string   $plan_name Membership plan name.
	 * @return string
	 */
	private static function get_payment_confirmation_template( \WP_User $user, float $amount, string $plan_name ): string {
		$dashboard_url = home_url( '/member-dashboard/' );
		$site_name = get_bloginfo( 'name' );

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<title><?php echo esc_html( $site_name ); ?></title>
		</head>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
				<h1 style="color: #0073aa;"><?php echo esc_html( $site_name ); ?></h1>
				
				<h2><?php esc_html_e( 'Payment Confirmation', 'lnmc-member-hub' ); ?></h2>
				
				<p><?php printf( esc_html__( 'Hi %s,', 'lnmc-member-hub' ), esc_html( $user->display_name ) ); ?></p>
				
				<p><?php esc_html_e( 'Thank you for your payment! Your transaction has been processed successfully.', 'lnmc-member-hub' ); ?></p>
				
				<div style="background-color: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 5px;">
					<h3><?php esc_html_e( 'Payment Details', 'lnmc-member-hub' ); ?></h3>
					<p><strong><?php esc_html_e( 'Amount:', 'lnmc-member-hub' ); ?></strong> $<?php echo esc_html( number_format( $amount, 2 ) ); ?></p>
					<?php if ( ! empty( $plan_name ) ) : ?>
						<p><strong><?php esc_html_e( 'Plan:', 'lnmc-member-hub' ); ?></strong> <?php echo esc_html( $plan_name ); ?></p>
					<?php endif; ?>
					<p><strong><?php esc_html_e( 'Date:', 'lnmc-member-hub' ); ?></strong> <?php echo esc_html( current_time( 'F j, Y' ) ); ?></p>
				</div>
				
				<p>
					<a href="<?php echo esc_url( $dashboard_url ); ?>" style="background-color: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">
						<?php esc_html_e( 'Access Your Dashboard', 'lnmc-member-hub' ); ?>
					</a>
				</p>
				
				<p><?php esc_html_e( 'Thank you for your continued support!', 'lnmc-member-hub' ); ?></p>
				
				<p><?php esc_html_e( 'Best regards,', 'lnmc-member-hub' ); ?><br>
				<?php echo esc_html( $site_name ); ?> Team</p>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get renewal reminder email template.
	 *
	 * @param \WP_User $user User object.
	 * @param string   $renewal_date Renewal date.
	 * @return string
	 */
	private static function get_renewal_reminder_template( \WP_User $user, string $renewal_date ): string {
		$dashboard_url = home_url( '/member-dashboard/' );
		$site_name = get_bloginfo( 'name' );

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<title><?php echo esc_html( $site_name ); ?></title>
		</head>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
				<h1 style="color: #0073aa;"><?php echo esc_html( $site_name ); ?></h1>
				
				<h2><?php esc_html_e( 'Membership Renewal Reminder', 'lnmc-member-hub' ); ?></h2>
				
				<p><?php printf( esc_html__( 'Hi %s,', 'lnmc-member-hub' ), esc_html( $user->display_name ) ); ?></p>
				
				<p><?php esc_html_e( 'This is a friendly reminder that your membership will be renewed on:', 'lnmc-member-hub' ); ?></p>
				
				<div style="background-color: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 5px;">
					<h3 style="color: #0073aa;"><?php echo esc_html( $renewal_date ); ?></h3>
				</div>
				
				<p><?php esc_html_e( 'To ensure uninterrupted access to our community and resources, please make sure your payment method is up to date.', 'lnmc-member-hub' ); ?></p>
				
				<p>
					<a href="<?php echo esc_url( $dashboard_url ); ?>" style="background-color: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">
						<?php esc_html_e( 'Update Payment Method', 'lnmc-member-hub' ); ?>
					</a>
				</p>
				
				<p><?php esc_html_e( 'If you have any questions, please don\'t hesitate to contact us.', 'lnmc-member-hub' ); ?></p>
				
				<p><?php esc_html_e( 'Best regards,', 'lnmc-member-hub' ); ?><br>
				<?php echo esc_html( $site_name ); ?> Team</p>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get admin new member notification template.
	 *
	 * @param \WP_User $user User object.
	 * @param string   $plan_name Membership plan name.
	 * @return string
	 */
	private static function get_admin_new_member_template( \WP_User $user, string $plan_name ): string {
		$admin_url = admin_url( 'admin.php?page=lnmc-member-hub-members' );
		$site_name = get_bloginfo( 'name' );

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<title><?php echo esc_html( $site_name ); ?></title>
		</head>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
				<h1 style="color: #0073aa;"><?php echo esc_html( $site_name ); ?></h1>
				
				<h2><?php esc_html_e( 'New Member Registration', 'lnmc-member-hub' ); ?></h2>
				
				<p><?php esc_html_e( 'A new member has joined your community!', 'lnmc-member-hub' ); ?></p>
				
				<div style="background-color: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 5px;">
					<h3><?php esc_html_e( 'Member Details', 'lnmc-member-hub' ); ?></h3>
					<p><strong><?php esc_html_e( 'Name:', 'lnmc-member-hub' ); ?></strong> <?php echo esc_html( $user->display_name ); ?></p>
					<p><strong><?php esc_html_e( 'Email:', 'lnmc-member-hub' ); ?></strong> <?php echo esc_html( $user->user_email ); ?></p>
					<p><strong><?php esc_html_e( 'Registration Date:', 'lnmc-member-hub' ); ?></strong> <?php echo esc_html( date( 'F j, Y', strtotime( $user->user_registered ) ) ); ?></p>
					<?php if ( ! empty( $plan_name ) ) : ?>
						<p><strong><?php esc_html_e( 'Plan:', 'lnmc-member-hub' ); ?></strong> <?php echo esc_html( $plan_name ); ?></p>
					<?php endif; ?>
				</div>
				
				<p>
					<a href="<?php echo esc_url( $admin_url ); ?>" style="background-color: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">
						<?php esc_html_e( 'View Member Details', 'lnmc-member-hub' ); ?>
					</a>
				</p>
				
				<p><?php esc_html_e( 'This is an automated notification from your membership system.', 'lnmc-member-hub' ); ?></p>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get email headers.
	 *
	 * @return array
	 */
	private static function get_email_headers(): array {
		$site_name = get_bloginfo( 'name' );
		$admin_email = get_option( 'admin_email' );

		return array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $site_name . ' <' . $admin_email . '>',
			'Reply-To: ' . $admin_email,
		);
	}
}
