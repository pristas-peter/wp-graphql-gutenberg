<?php

namespace WPGraphQLGutenberg\Server;

use GraphQL\Error\ClientAware;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Language\Visitor;
use WPGraphQLGutenberg\PostTypes\BlockEditorPreview;

if (!defined('WP_GRAPHQL_GUTENBERG_SERVER_URL')) {
	define('WP_GRAPHQL_GUTENBERG_SERVER_URL', null);
}

if (!defined('WP_GRAPHQL_GUTENBERG_ENABLE_SERVER')) {
	define('WP_GRAPHQL_GUTENBERG_ENABLE_SERVER', !empty(WP_GRAPHQL_GUTENBERG_SERVER_URL));
}

if (!defined('WP_GRAPHQL_GUTENBERG_SERVER_USER_ROLE')) {
	define('WP_GRAPHQL_GUTENBERG_SERVER_USER_ROLE', 'wp_graphql_gutenberg_server_editor');
}

if (!defined('WP_GRAPHQL_GUTENBERG_SERVER_USER_ROLE_DISPLAY_NAME')) {
	define(
		'WP_GRAPHQL_GUTENBERG_SERVER_USER_ROLE_DISPLAY_NAME',
		__('WP GraphQL Gutenberg Server User', 'wp-graphql-gutenberg')
	);
}

if (!defined('WP_GRAPHQL_GUTENBERG_SERVER_USER_LOGIN')) {
	define('WP_GRAPHQL_GUTENBERG_SERVER_USER_LOGIN', WP_GRAPHQL_GUTENBERG_SERVER_USER_ROLE);
}

if (!defined('WP_GRAPHQL_GUTENBERG_SERVER_USER_DISPLAY_NAME')) {
	define('WP_GRAPHQL_GUTENBERG_SERVER_USER_DISPLAY_NAME', WP_GRAPHQL_GUTENBERG_SERVER_USER_ROLE_DISPLAY_NAME);
}

if (!defined('WP_GRAPHQL_GUTENBERG_ENABLE_SERVER')) {
	define('WP_GRAPHQL_GUTENBERG_ENABLE_SERVER', !empty(WP_GRAPHQL_GUTENBERG_SERVER_URL));
}

class ServerException extends \Exception implements ClientAware {
	public function isClientSafe() {
		return false;
	}

	public function getCategory() {
		return 'gutenberg-server';
	}
}

class Server {
	private static function create_server_user_role() {
		$edit_posts = get_post_type_object(BlockEditorPreview::post_type())->cap->edit_posts;

		add_role(WP_GRAPHQL_GUTENBERG_SERVER_USER_ROLE, WP_GRAPHQL_GUTENBERG_SERVER_USER_ROLE_DISPLAY_NAME, [
			'read' => true,
			$edit_posts => true
		]);
	}

	private static function ensure_server_user_role() {
		if (!get_role(WP_GRAPHQL_GUTENBERG_SERVER_USER_ROLE)) {
			self::create_server_user_role();
		}
	}

	private static function create_server_user() {
		self::ensure_server_user_role();

		return wp_insert_user([
			'user_login' => WP_GRAPHQL_GUTENBERG_SERVER_USER_LOGIN,
			'display_name' => WP_GRAPHQL_GUTENBERG_SERVER_USER_DISPLAY_NAME,
			'user_pass' => wp_generate_password(),
			'role' => WP_GRAPHQL_GUTENBERG_SERVER_USER_ROLE,
			'show_admin_bar_front' => false
		]);
	}

	private static function get_server_user() {
		return get_user_by('login', WP_GRAPHQL_GUTENBERG_SERVER_USER_LOGIN);
	}

	private static function ensure_server_user() {
		$user = self::get_server_user();

		if (empty($user)) {
			return get_user_by('id', self::create_server_user());
		}

		return $user;
	}

	private static function delete_server_user_role() {
		if (get_role(WP_GRAPHQL_GUTENBERG_SERVER_USER_ROLE)) {
			remove_role(WP_GRAPHQL_GUTENBERG_SERVER_USER_ROLE);
		}
	}

	private static function delete_server_user() {
		$user = self::get_server_user();

		if (!empty($user)) {
			wp_delete_user($user->ID);
		}
	}

	public static function cleanup() {
		self::delete_server_user();
		self::delete_server_user_role();
	}

	public static function user() {
		return self::ensure_server_user();
	}

	public function enabled() {
		return WP_GRAPHQL_GUTENBERG_ENABLE_SERVER && WP_GRAPHQL_GUTENBERG_SERVER_URL;
	}

	public function url() {
		return WP_GRAPHQL_GUTENBERG_SERVER_URL;
	}

	public function gutenberg_fields_in_query($query) {
		$context = ['result' => false];

		Visitor::visit(Parser::parse(new Source($query, 'GraphQL')), [
			NodeKind::FIELD => [
				'enter' => function ($definition) use (&$context) {
					if (
						in_array(
							$definition->name->value,
							[
								// introspection
								'__schema',
								// BlockEditorContentNode interface
								'blockEditorContentNodes',
								'blocks',
								'blocksJSON',
								// ReusableBlock type
								'reusableBlock',
								'reusableBlocks'
								// previews are stored in DB, so should be safe
								// 'previewBlocks', 'previewBlocksJSON','blockEditorPreview', 'blockEditorPreviews',
							],
							true
						)
					) {
						$context['result'] = true;
						return Visitor::stop();
					}

					return null;
				}
			]
		]);

		return apply_filters('graphql_gutenberg_server_fields_in_query', $context['result']);
	}

	protected function fetch($path, $data = []) {
		$secure = is_ssl() && 'https' === parse_url(get_option('home'), PHP_URL_SCHEME);
		$expiration = time() + 60;
		$cookie = wp_generate_auth_cookie(
			self::ensure_server_user()->ID,
			$expiration,
			$secure ? 'secure_auth' : 'auth'
		);
		$url =
			get_admin_url() .
			'post-new.php?post_type=' .
			BlockEditorPreview::post_type() .
			'&wpGraphqlGutenbergServer=true';

		$result = wp_remote_post($this->url() . $path, [
			'headers' => [
				'Content-Type' => 'application/json; charset=utf-8'
			],
			'body' => json_encode(
				array_merge($data, [
					'url' => $url,
					'cookies' => [
						[
							'name' => $secure ? SECURE_AUTH_COOKIE : AUTH_COOKIE,
							'value' => $cookie,
							'httpOnly' => true,
							'secure' => $secure,
							'path' => ADMIN_COOKIE_PATH,
							'domain' => parse_url($url, PHP_URL_HOST),
							'expire' => $expiration
						]
					]
				])
			),
			'compress' => true
		]);

		if (is_wp_error($result)) {
			throw new ServerException(join(', ', $result->get_error_messages()));
		}

		$json = json_decode($result['body'], true, JSON_THROW_ON_ERROR);

		if (200 !== $result['response']['code']) {
			throw new ServerException($json['error']['message']);
		}

		return $json;
	}

	public function get_block_types() {
		return $this->fetch('/block-types');
	}
}
