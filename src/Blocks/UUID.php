<?php

namespace WPGraphQLGutenberg\Blocks;

class UUIDGenerator {
	private $key;
	private $prefix;

	public function __construct($id) {
		$this->key = 0;

		$prefix_chars = [];

		$id_pad = str_pad(strval($id), 20, '0', STR_PAD_LEFT);

		for ($i = 0; $i < strlen($id_pad); $i++) {
			$prefix_chars[] = $id_pad[$i];

			if ($i === 7 || $i === 11 || $i === 15) {
				$prefix_chars[] = '-';
			}
		}

		$this->prefix = join('', $prefix_chars);
	}

	public function create_uuid() {
		return $this->prefix . '-' . str_pad(strval($this->key++), 12, '0', STR_PAD_LEFT);
	}
}
