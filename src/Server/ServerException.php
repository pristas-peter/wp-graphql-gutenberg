<?php
namespace WPGraphQLGutenberg\Server;

use GraphQL\Error\ClientAware;

class ServerException extends \Exception implements ClientAware {
	public function isClientSafe(): bool {
		return false;
	}

	public function getCategory(): string {
		return 'gutenberg-server';
	}
}
