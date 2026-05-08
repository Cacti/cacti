<?php declare(strict_types = 1);

namespace PHPStan\PhpDocParser\Ast\Type;

use PHPStan\PhpDocParser\Ast\NodeAttributes;

class OffsetAccessTypeNode implements TypeNode
{

	use NodeAttributes;

	public TypeNode $type;

	public TypeNode $offset;

	public function __construct(TypeNode $type, TypeNode $offset)
	{
		$this->type = $type;
		$this->offset = $offset;
	}

	public function __toString(): string
	{
		if (
			$this->type instanceof CallableTypeNode
			|| $this->type instanceof NullableTypeNode
		) {
			return '(' . $this->type . ')[' . $this->offset . ']';
		}

		return $this->type . '[' . $this->offset . ']';
	}

	/**
	 * @param array<string, mixed> $properties
	 */
	public static function __set_state(array $properties): self
	{
		$instance = new self($properties['type'], $properties['offset']);
		if (isset($properties['attributes'])) {
			foreach ($properties['attributes'] as $key => $value) {
				$instance->setAttribute($key, $value);
			}
		}
		return $instance;
	}

}
