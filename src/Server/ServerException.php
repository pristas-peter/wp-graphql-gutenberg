<?php
namespace WPGraphQLGutenberg\Server;

class ServerException extends \Exception implements ClientAware {
	public function isClientSafe() {
		return false;
	}

	public function getCategory() {
		return 'gutenberg-server';
	}
}
