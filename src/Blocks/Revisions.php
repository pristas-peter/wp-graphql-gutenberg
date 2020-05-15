<?php

namespace WPGraphQLGutenberg\Blocks;
use Ramsey\Uuid\Uuid;

class Revisions {
	public function __construct() {
		add_action(
			'wp_restore_post_revision',
			function ($post_id, $revision_id) {
				$post = get_post($post_id);

				if (has_blocks($post->post_content)) {
					$state = [
						'did_update' => false
					];

					$blocks = Utils::visit_blocks(parse_blocks($post->post_content), function ($block) use (&$state) {
						if (!empty($block['blockName'] && empty($block['attrs']['wpGraphqlUUID']))) {
							$state['did_update'] = true;
							$block['attrs']['wpGraphqlUUID'] = Uuid::uuid4();
						}
						return $block;
					});

					if ($state['did_update']) {
						wp_update_post([
							'ID' => $post_id,
							'post_content' => serialize_blocks($blocks)
						]);
					}
				}
			},
			10,
			2
		);
	}
}
