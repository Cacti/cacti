<?php declare(strict_types = 1);

namespace PHPStan\PhpDocParser\Ast\Type;

use PHPStan\PhpDocParser\Ast\NodeAttributes;
use function sprintf;

class ConditionalTypeForParameterNode implements TypeNode
{

	use NodeAttributes;

	public string $parameterName;

	public TypeNode $targetType;

	public TypeNode $if;

	public TypeNode $else;

	public bool $negated;

	public function __construct(string $parameterName, TypeNode $targetType, TypeNode $if, TypeNode $else, bool $negated)
	{
		$this->parameterName = $parameterName;
		$this->targetType = $targetType;
		$this->if = $if;
		$this->else = $else;
		$this->negated = $negated;
	}

	public function __toString(): string
	{
		return sprintf(
			'(%s %s %s ? %s : %s)',
			$this->parameterName,
			$this->negated ? 'is not' : 'is',
			$this->targetType,
			$this->if,
			$this->else,
		);
	}

	/**
	 * @param array<string, mixed> $properties
	 */
	public static function __set_state(array $properties): self
	{
		$instance = new self($properties['parameterName'], $properties['targetType'], $properties['if'], $properties['else'], $properties['negated']);
		if (isset($properties['attributes'])) {
			foreach ($properties['attributes'] as $key => $value) {
				$instance->setAttribute($key, $value);
			}
		}
		return $instance;
	}

}
