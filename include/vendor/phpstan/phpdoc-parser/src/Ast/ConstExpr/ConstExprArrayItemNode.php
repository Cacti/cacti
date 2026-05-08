<?php declare(strict_types = 1);

namespace PHPStan\PhpDocParser\Ast\ConstExpr;

use PHPStan\PhpDocParser\Ast\NodeAttributes;
use function sprintf;

class ConstExprArrayItemNode implements ConstExprNode
{

	use NodeAttributes;

	public ?ConstExprNode $key = null;

	public ConstExprNode $value;

	public function __construct(?ConstExprNode $key, ConstExprNode $value)
	{
		$this->key = $key;
		$this->value = $value;
	}

	public function __toString(): string
	{
		if ($this->key !== null) {
			return sprintf('%s => %s', $this->key, $this->value);

		}

		return (string) $this->value;
	}

	/**
	 * @param array<string, mixed> $properties
	 */
	public static function __set_state(array $properties): self
	{
		$instance = new self($properties['key'], $properties['value']);
		if (isset($properties['attributes'])) {
			foreach ($properties['attributes'] as $key => $value) {
				$instance->setAttribute($key, $value);
			}
		}
		return $instance;
	}

}
