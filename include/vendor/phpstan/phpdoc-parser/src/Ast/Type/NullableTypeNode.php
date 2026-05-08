<?php declare(strict_types = 1);

namespace PHPStan\PhpDocParser\Ast\Type;

use PHPStan\PhpDocParser\Ast\NodeAttributes;

class NullableTypeNode implements TypeNode
{

	use NodeAttributes;

	public TypeNode $type;

	public function __construct(TypeNode $type)
	{
		$this->type = $type;
	}

	public function __toString(): string
	{
		return '?' . $this->type;
	}

	/**
	 * @param array<string, mixed> $properties
	 */
	public static function __set_state(array $properties): self
	{
		$instance = new self($properties['type']);
		if (isset($properties['attributes'])) {
			foreach ($properties['attributes'] as $key => $value) {
				$instance->setAttribute($key, $value);
			}
		}
		return $instance;
	}

}
