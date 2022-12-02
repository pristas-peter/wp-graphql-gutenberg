<?php

namespace WPGraphQLGutenberg\Blocks;

use GraphQL\Error\ClientAware;

class RegistryNotSourcedException extends \Exception implements ClientAware {
	public function isClientSafe() {
		return true;
	}

	public function getCategory() {
		return 'gutenberg';
	}
}
