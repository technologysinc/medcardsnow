<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema\Elements;

use Nette;
use Nette\Schema\{Context, Helpers, Kind, Schema};
use function is_array;


/**
 * A fixed-size array where each position has its own schema; a later layer replaces the tuple wholesale.
 */
final class TupleType extends Structure
{
	/** @param Schema[]  $shape */
	public function __construct(array $shape)
	{
		if (!array_is_list($shape)) {
			throw new Nette\InvalidArgumentException('Tuple shape must be indexed array.');
		}

		parent::__construct($shape);
		$this->castTo('array');
	}


	/**
	 * Appends positions to the tuple.
	 * @param  Schema[]|Structure  $shape
	 */
	public function extend(array|Structure $shape): self
	{
		$shape = $shape instanceof Structure ? $shape->getShape() : $shape;
		return new self([...$this->getShape(), ...$shape]);
	}


	public function merge(mixed $value, mixed $base): mixed
	{
		if (is_array($value) && isset($value[Helpers::PreventMerging])) {
			unset($value[Helpers::PreventMerging]);
		}

		return $value;
	}


	public function complete(mixed $value, Context $context): mixed
	{
		if (is_array($value)) {
			unset($value[Helpers::PreventMerging]);
		}

		if (is_array($value) && !array_is_list($value)) { // positions carry meaning, so no keys and no other order
			$context->addError(
				'The %label% %path% expects to be list, %value% given.',
				Nette\Schema\Message::TypeMismatch,
				['value' => $value, 'expected' => 'list'],
			);
			return null;
		}

		return parent::complete($value, $context);
	}


	public function describe(): array
	{
		return ['kind' => Kind::Tuple] + parent::describe();
	}


	/**
	 * A tuple has a fixed size and its positions carry meaning, so an empty block is not a tuple of defaults.
	 */
	protected function coerce(mixed $value): mixed
	{
		return $value;
	}
}
