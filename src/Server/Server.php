<?php

namespace WPGraphQLGutenberg\Server;

use GraphQL\GraphQL;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Language\Visitor;
use WPGraphQLGutenberg\Blocks\Registry;
use WPGraphQLGutenberg\PostTypes\BlockEditorPreview;


if (!defined('WP_GRAPHQL_GUTENBERG_SERVER_URL')) {
    define('WP_GRAPHQL_GUTENBERG_SERVER_URL', null);
}

if (!defined('WP_GRAPHQL_GUTENBERG_ENABLE_SERVER')) {
    define('WP_GRAPHQL_GUTENBERG_ENABLE_SERVER', !empty(WP_GRAPHQL_GUTENBERG_SERVER_URL));
}

class Server
{
    public function enabled()
    {
        return WP_GRAPHQL_GUTENBERG_ENABLE_SERVER && WP_GRAPHQL_GUTENBERG_SERVER_URL;
    }

    public function url()
    {
        return WP_GRAPHQL_GUTENBERG_SERVER_URL;
    }

    public function gutenberg_fields_in_query($query)
    {
        $context = ['result' => false];

        Visitor::visit(Parser::parse(new Source($query, 'GraphQL')), [
            NodeKind::FIELD => [
                'enter' => function ($definition) use (&$context) {
                    if (in_array($definition->name->value, [
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
                    ])) {
                        $context['result'] = true;
                        return Visitor::stop();
                    }

                    return null;
                }
            ]
        ]);

        return apply_filters('graphql_gutenberg_server_fields_in_query', $context['result']);
    }

    protected function fetch($path, $data = [])
    {

        $secure = is_ssl() && 'https' === parse_url(get_option('home'), PHP_URL_SCHEME);
        $expiration = time() + 60;
        $cookie = wp_generate_auth_cookie(get_current_user_id(), $expiration, $secure ? 'secure_auth' : 'auth');
        $url = get_admin_url() . 'post-new.php?post_type=' . BlockEditorPreview::post_type() . '&wpGraphqlGutenbergServer=true';

        $result = wp_remote_post($this->url() . $path, [
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8'
            ],
            'body' => json_encode(array_merge($data, [
                'url' => $url,
                'cookies' => [[
                    'name' => $secure ? SECURE_AUTH_COOKIE : AUTH_COOKIE,
                    'value' => $cookie,
                    'httpOnly' => true,
                    'secure' => $secure,
                    'path' => ADMIN_COOKIE_PATH,
                    'domain' => parse_url($url, PHP_URL_HOST),
                    'expire' => $expiration
                ]]
            ])),
            'compress' => true,
        ]);

        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_messages());
        }

        $json = json_decode($result['body'], true, JSON_THROW_ON_ERROR);

        if ($result['response']['code'] !== 200) {
            throw new \Exception($json['error']['message']);
        }

        return $json;
    }


    public function get_block_types()
    {
        return $this->fetch('/block-types');
    }

    public function get_batch($post_content_by_id)
    {
        return $this->fetch('/batch', ['contentById' => $post_content_by_id]);
    }
}
