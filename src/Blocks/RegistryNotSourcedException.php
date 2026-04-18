<?php

namespace WPGraphQLGutenberg\Blocks;

use GraphQL\Error\ClientAware;

class RegistryNotSourcedException extends \Exception implements ClientAware {
	public function isClientSafe(): bool {
		return true;
	}

	public function getCategory(): string {
		return 'gutenberg';
	}
}
