<?php

declare(strict_types=1);

namespace Pest\Mutate;

use Pest\Mutate\Mutators\Arithmetic\DivisionToMultiplication;
use Pest\Mutate\Mutators\Arithmetic\MinusToPlus;
use Pest\Mutate\Mutators\Arithmetic\ModulusToMultiplication;
use Pest\Mutate\Mutators\Arithmetic\MultiplicationToDivision;
use Pest\Mutate\Mutators\Arithmetic\PlusToMinus;
use Pest\Mutate\Mutators\Arithmetic\PostDecrementToPostIncrement;
use Pest\Mutate\Mutators\Arithmetic\PostIncrementToPostDecrement;
use Pest\Mutate\Mutators\Arithmetic\PowerToMultiplication;
use Pest\Mutate\Mutators\Arithmetic\PreDecrementToPreIncrement;
use Pest\Mutate\Mutators\Arithmetic\PreIncrementToPreDecrement;
use Pest\Mutate\Mutators\Array\ArrayKeyFirstToArrayKeyLast;
use Pest\Mutate\Mutators\Array\ArrayKeyLastToArrayKeyFirst;
use Pest\Mutate\Mutators\Array\ArrayPopToArrayShift;
use Pest\Mutate\Mutators\Array\ArrayShiftToArrayPop;
use Pest\Mutate\Mutators\Array\UnwrapArrayChangeKeyCase;
use Pest\Mutate\Mutators\Array\UnwrapArrayChunk;
use Pest\Mutate\Mutators\Array\UnwrapArrayColumn;
use Pest\Mutate\Mutators\Array\UnwrapArrayCombine;
use Pest\Mutate\Mutators\Array\UnwrapArrayCountValues;
use Pest\Mutate\Mutators\Array\UnwrapArrayDiff;
use Pest\Mutate\Mutators\Array\UnwrapArrayDiffAssoc;
use Pest\Mutate\Mutators\Array\UnwrapArrayDiffKey;
use Pest\Mutate\Mutators\Array\UnwrapArrayDiffUassoc;
use Pest\Mutate\Mutators\Array\UnwrapArrayDiffUkey;
use Pest\Mutate\Mutators\Array\UnwrapArrayFilter;
use Pest\Mutate\Mutators\Array\UnwrapArrayFlip;
use Pest\Mutate\Mutators\Array\UnwrapArrayIntersect;
use Pest\Mutate\Mutators\Array\UnwrapArrayIntersectAssoc;
use Pest\Mutate\Mutators\Array\UnwrapArrayIntersectKey;
use Pest\Mutate\Mutators\Array\UnwrapArrayIntersectUassoc;
use Pest\Mutate\Mutators\Array\UnwrapArrayIntersectUkey;
use Pest\Mutate\Mutators\Array\UnwrapArrayKeys;
use Pest\Mutate\Mutators\Array\UnwrapArrayMap;
use Pest\Mutate\Mutators\Array\UnwrapArrayMerge;
use Pest\Mutate\Mutators\Array\UnwrapArrayMergeRecursive;
use Pest\Mutate\Mutators\Array\UnwrapArrayPad;
use Pest\Mutate\Mutators\Array\UnwrapArrayReduce;
use Pest\Mutate\Mutators\Array\UnwrapArrayReplace;
use Pest\Mutate\Mutators\Array\UnwrapArrayReplaceRecursive;
use Pest\Mutate\Mutators\Array\UnwrapArrayReverse;
use Pest\Mutate\Mutators\Array\UnwrapArraySlice;
use Pest\Mutate\Mutators\Array\UnwrapArraySplice;
use Pest\Mutate\Mutators\Array\UnwrapArrayUdiff;
use Pest\Mutate\Mutators\Array\UnwrapArrayUdiffAssoc;
use Pest\Mutate\Mutators\Array\UnwrapArrayUdiffUassoc;
use Pest\Mutate\Mutators\Array\UnwrapArrayUintersect;
use Pest\Mutate\Mutators\Array\UnwrapArrayUintersectAssoc;
use Pest\Mutate\Mutators\Array\UnwrapArrayUintersectUassoc;
use Pest\Mutate\Mutators\Array\UnwrapArrayUnique;
use Pest\Mutate\Mutators\Array\UnwrapArrayValues;
use Pest\Mutate\Mutators\Assignment\BitwiseAndToBitwiseOr;
use Pest\Mutate\Mutators\Assignment\BitwiseOrToBitwiseAnd;
use Pest\Mutate\Mutators\Assignment\BitwiseXorToBitwiseAnd;
use Pest\Mutate\Mutators\Assignment\CoalesceEqualToEqual;
use Pest\Mutate\Mutators\Assignment\ConcatEqualToEqual;
use Pest\Mutate\Mutators\Assignment\DivideEqualToMultiplyEqual;
use Pest\Mutate\Mutators\Assignment\MinusEqualToPlusEqual;
use Pest\Mutate\Mutators\Assignment\ModulusEqualToMultiplyEqual;
use Pest\Mutate\Mutators\Assignment\MultiplyEqualToDivideEqual;
use Pest\Mutate\Mutators\Assignment\PlusEqualToMinusEqual;
use Pest\Mutate\Mutators\Assignment\PowerEqualToMultiplyEqual;
use Pest\Mutate\Mutators\Assignment\ShiftLeftToShiftRight;
use Pest\Mutate\Mutators\Assignment\ShiftRightToShiftLeft;
use Pest\Mutate\Mutators\Casting\RemoveArrayCast;
use Pest\Mutate\Mutators\Casting\RemoveBooleanCast;
use Pest\Mutate\Mutators\Casting\RemoveDoubleCast;
use Pest\Mutate\Mutators\Casting\RemoveIntegerCast;
use Pest\Mutate\Mutators\Casting\RemoveObjectCast;
use Pest\Mutate\Mutators\Casting\RemoveStringCast;
use Pest\Mutate\Mutators\ControlStructures\BreakToContinue;
use Pest\Mutate\Mutators\ControlStructures\ContinueToBreak;
use Pest\Mutate\Mutators\ControlStructures\DoWhileAlwaysFalse;
use Pest\Mutate\Mutators\ControlStructures\ElseIfNegated;
use Pest\Mutate\Mutators\ControlStructures\ForAlwaysFalse;
use Pest\Mutate\Mutators\ControlStructures\ForeachEmptyIterable;
use Pest\Mutate\Mutators\ControlStructures\IfNegated;
use Pest\Mutate\Mutators\ControlStructures\TernaryNegated;
use Pest\Mutate\Mutators\ControlStructures\WhileAlwaysFalse;
use Pest\Mutate\Mutators\Equality\EqualToIdentical;
use Pest\Mutate\Mutators\Equality\EqualToNotEqual;
use Pest\Mutate\Mutators\Equality\GreaterOrEqualToGreater;
use Pest\Mutate\Mutators\Equality\GreaterOrEqualToSmaller;
use Pest\Mutate\Mutators\Equality\GreaterToGreaterOrEqual;
use Pest\Mutate\Mutators\Equality\GreaterToSmallerOrEqual;
use Pest\Mutate\Mutators\Equality\IdenticalToEqual;
use Pest\Mutate\Mutators\Equality\IdenticalToNotIdentical;
use Pest\Mutate\Mutators\Equality\NotEqualToEqual;
use Pest\Mutate\Mutators\Equality\NotEqualToNotIdentical;
use Pest\Mutate\Mutators\Equality\NotIdenticalToIdentical;
use Pest\Mutate\Mutators\Equality\NotIdenticalToNotEqual;
use Pest\Mutate\Mutators\Equality\SmallerOrEqualToGreater;
use Pest\Mutate\Mutators\Equality\SmallerOrEqualToSmaller;
use Pest\Mutate\Mutators\Equality\SmallerToGreaterOrEqual;
use Pest\Mutate\Mutators\Equality\SmallerToSmallerOrEqual;
use Pest\Mutate\Mutators\Equality\SpaceshipSwitchSides;
use Pest\Mutate\Mutators\Laravel\Remove\LaravelRemoveStringableUpper;
use Pest\Mutate\Mutators\Laravel\Unwrap\LaravelUnwrapStrUpper;
use Pest\Mutate\Mutators\Logical\BooleanAndToBooleanOr;
use Pest\Mutate\Mutators\Logical\BooleanOrToBooleanAnd;
use Pest\Mutate\Mutators\Logical\CoalesceRemoveLeft;
use Pest\Mutate\Mutators\Logical\FalseToTrue;
use Pest\Mutate\Mutators\Logical\InstanceOfToFalse;
use Pest\Mutate\Mutators\Logical\InstanceOfToTrue;
use Pest\Mutate\Mutators\Logical\LogicalAndToLogicalOr;
use Pest\Mutate\Mutators\Logical\LogicalOrToLogicalAnd;
use Pest\Mutate\Mutators\Logical\LogicalXorToLogicalAnd;
use Pest\Mutate\Mutators\Logical\RemoveNot;
use Pest\Mutate\Mutators\Logical\TrueToFalse;
use Pest\Mutate\Mutators\Math\CeilToFloor;
use Pest\Mutate\Mutators\Math\CeilToRound;
use Pest\Mutate\Mutators\Math\FloorToCiel;
use Pest\Mutate\Mutators\Math\FloorToRound;
use Pest\Mutate\Mutators\Math\MaxToMin;
use Pest\Mutate\Mutators\Math\MinToMax;
use Pest\Mutate\Mutators\Math\RoundToCeil;
use Pest\Mutate\Mutators\Math\RoundToFloor;
use Pest\Mutate\Mutators\Number\DecrementFloat;
use Pest\Mutate\Mutators\Number\DecrementInteger;
use Pest\Mutate\Mutators\Number\IncrementFloat;
use Pest\Mutate\Mutators\Number\IncrementInteger;
use Pest\Mutate\Mutators\Removal\RemoveArrayItem;
use Pest\Mutate\Mutators\Removal\RemoveEarlyReturn;
use Pest\Mutate\Mutators\Removal\RemoveFunctionCall;
use Pest\Mutate\Mutators\Removal\RemoveMethodCall;
use Pest\Mutate\Mutators\Removal\RemoveNullSafeOperator;
use Pest\Mutate\Mutators\Return\AlwaysReturnEmptyArray;
use Pest\Mutate\Mutators\Return\AlwaysReturnNull;
use Pest\Mutate\Mutators\Sets\ArithmeticSet;
use Pest\Mutate\Mutators\Sets\ArraySet;
use Pest\Mutate\Mutators\Sets\AssignmentSet;
use Pest\Mutate\Mutators\Sets\CastingSet;
use Pest\Mutate\Mutators\Sets\ControlStructuresSet;
use Pest\Mutate\Mutators\Sets\DefaultSet;
use Pest\Mutate\Mutators\Sets\EqualitySet;
use Pest\Mutate\Mutators\Sets\LaravelSet;
use Pest\Mutate\Mutators\Sets\LogicalSet;
use Pest\Mutate\Mutators\Sets\MathSet;
use Pest\Mutate\Mutators\Sets\NumberSet;
use Pest\Mutate\Mutators\Sets\RemovalSet;
use Pest\Mutate\Mutators\Sets\ReturnSet;
use Pest\Mutate\Mutators\Sets\StringSet;
use Pest\Mutate\Mutators\Sets\VisibilitySet;
use Pest\Mutate\Mutators\String\ConcatRemoveLeft;
use Pest\Mutate\Mutators\String\ConcatRemoveRight;
use Pest\Mutate\Mutators\String\ConcatSwitchSides;
use Pest\Mutate\Mutators\String\EmptyStringToNotEmpty;
use Pest\Mutate\Mutators\String\NotEmptyStringToEmpty;
use Pest\Mutate\Mutators\String\StrEndsWithToStrStartsWith;
use Pest\Mutate\Mutators\String\StrStartsWithToStrEndsWith;
use Pest\Mutate\Mutators\String\UnwrapChop;
use Pest\Mutate\Mutators\String\UnwrapChunkSplit;
use Pest\Mutate\Mutators\String\UnwrapHtmlentities;
use Pest\Mutate\Mutators\String\UnwrapHtmlEntityDecode;
use Pest\Mutate\Mutators\String\UnwrapHtmlspecialchars;
use Pest\Mutate\Mutators\String\UnwrapHtmlspecialcharsDecode;
use Pest\Mutate\Mutators\String\UnwrapLcfirst;
use Pest\Mutate\Mutators\String\UnwrapLtrim;
use Pest\Mutate\Mutators\String\UnwrapMd5;
use Pest\Mutate\Mutators\String\UnwrapNl2br;
use Pest\Mutate\Mutators\String\UnwrapRtrim;
use Pest\Mutate\Mutators\String\UnwrapStripTags;
use Pest\Mutate\Mutators\String\UnwrapStrIreplace;
use Pest\Mutate\Mutators\String\UnwrapStrPad;
use Pest\Mutate\Mutators\String\UnwrapStrRepeat;
use Pest\Mutate\Mutators\String\UnwrapStrReplace;
use Pest\Mutate\Mutators\String\UnwrapStrrev;
use Pest\Mutate\Mutators\String\UnwrapStrShuffle;
use Pest\Mutate\Mutators\String\UnwrapStrtolower;
use Pest\Mutate\Mutators\String\UnwrapStrtoupper;
use Pest\Mutate\Mutators\String\UnwrapSubstr;
use Pest\Mutate\Mutators\String\UnwrapTrim;
use Pest\Mutate\Mutators\String\UnwrapUcfirst;
use Pest\Mutate\Mutators\String\UnwrapUcwords;
use Pest\Mutate\Mutators\String\UnwrapWordwrap;
use Pest\Mutate\Mutators\Visibility\ConstantProtectedToPrivate;
use Pest\Mutate\Mutators\Visibility\ConstantPublicToProtected;
use Pest\Mutate\Mutators\Visibility\FunctionProtectedToPrivate;
use Pest\Mutate\Mutators\Visibility\FunctionPublicToProtected;
use Pest\Mutate\Mutators\Visibility\PropertyProtectedToPrivate;
use Pest\Mutate\Mutators\Visibility\PropertyPublicToProtected;

class Mutators
{
    /** Sets */
    final public const SET_DEFAULT = DefaultSet::class;

    final public const SET_ARITHMETIC = ArithmeticSet::class;

    final public const SET_ARRAY = ArraySet::class;

    final public const SET_ASSIGNMENT = AssignmentSet::class;

    final public const SET_CASTING = CastingSet::class;

    final public const SET_CONTROL_STRUCTURES = ControlStructuresSet::class;

    final public const SET_EQUALITY = EqualitySet::class;

    final public const SET_LOGICAL = LogicalSet::class;

    final public const SET_MATH = MathSet::class;

    final public const SET_NUMBER = NumberSet::class;

    final public const SET_REMOVAL = RemovalSet::class;

    final public const SET_RETURN = ReturnSet::class;

    final public const SET_STRING = StringSet::class;

    final public const SET_VISIBILITY = VisibilitySet::class;

    final public const SET_LARAVEL = LaravelSet::class;

    /** Arithmetic */
    final public const ARITHMETIC_BITWISE_AND_TO_BITWISE_OR = \Pest\Mutate\Mutators\Arithmetic\BitwiseAndToBitwiseOr::class;

    final public const ARITHMETIC_BITWISE_OR_TO_BITWISE_AND = \Pest\Mutate\Mutators\Arithmetic\BitwiseOrToBitwiseAnd::class;

    final public const ARITHMETIC_BITWISE_XOR_TO_BITWISE_AND = \Pest\Mutate\Mutators\Arithmetic\BitwiseXorToBitwiseAnd::class;

    final public const ARITHMETIC_PLUS_TO_MINUS = PlusToMinus::class;

    final public const ARITHMETIC_MINUS_TO_PLUS = MinusToPlus::class;

    final public const ARITHMETIC_DIVISION_TO_MULTIPLICATION = DivisionToMultiplication::class;

    final public const ARITHMETIC_MULTIPLICATION_TO_DIVISION = MultiplicationToDivision::class;

    final public const ARITHMETIC_MODULUS_TO_MULTIPLICATION = ModulusToMultiplication::class;

    final public const ARITHMETIC_POWER_TO_MULTIPLICATION = PowerToMultiplication::class;

    final public const ARITHMETIC_SHIFT_LEFT_TO_SHIFT_RIGHT = \Pest\Mutate\Mutators\Arithmetic\ShiftLeftToShiftRight::class;

    final public const ARITHMETIC_SHIFT_RIGHT_TO_SHIFT_LEFT = \Pest\Mutate\Mutators\Arithmetic\ShiftRightToShiftLeft::class;

    final public const ARITHMETIC_POST_DECREMENT_TO_POST_INCREMENT = PostDecrementToPostIncrement::class;

    final public const ARITHMETIC_POST_INCREMENT_TO_POST_DECREMENT = PostIncrementToPostDecrement::class;

    final public const ARITHMETIC_PRE_DECREMENT_TO_PRE_INCREMENT = PreDecrementToPreIncrement::class;

    final public const ARITHMETIC_PRE_INCREMENT_TO_PRE_DECREMENT = PreIncrementToPreDecrement::class;

    /** Array */
    final public const ARRAY_ARRAY_KEY_FIRST_TO_ARRAY_KEY_LAST = ArrayKeyFirstToArrayKeyLast::class;

    final public const ARRAY_ARRAY_KEY_LAST_TO_ARRAY_KEY_FIRST = ArrayKeyLastToArrayKeyFirst::class;

    final public const ARRAY_ARRAY_POP_TO_ARRAY_SHIFT = ArrayPopToArrayShift::class;

    final public const ARRAY_ARRAY_SHIFT_TO_ARRAY_POP = ArrayShiftToArrayPop::class;

    final public const ARRAY_UNWRAP_ARRAY_CHANGE_KEY_CASE = UnwrapArrayChangeKeyCase::class;

    final public const ARRAY_UNWRAP_ARRAY_CHUNK = UnwrapArrayChunk::class;

    final public const ARRAY_UNWRAP_ARRAY_COLUMN = UnwrapArrayColumn::class;

    final public const ARRAY_UNWRAP_ARRAY_COMBINE = UnwrapArrayCombine::class;

    final public const ARRAY_UNWRAP_ARRAY_COUNT_VALUES = UnwrapArrayCountValues::class;

    final public const ARRAY_UNWRAP_ARRAY_DIFF = UnwrapArrayDiff::class;

    final public const ARRAY_UNWRAP_ARRAY_DIFF_ASSOC = UnwrapArrayDiffAssoc::class;

    final public const ARRAY_UNWRAP_ARRAY_DIFF_KEY = UnwrapArrayDiffKey::class;

    final public const ARRAY_UNWRAP_ARRAY_DIFF_UASSOC = UnwrapArrayDiffUassoc::class;

    final public const ARRAY_UNWRAP_ARRAY_DIFF_UKEY = UnwrapArrayDiffUkey::class;

    final public const ARRAY_UNWRAP_ARRAY_FILTER = UnwrapArrayFilter::class;

    final public const ARRAY_UNWRAP_ARRAY_FLIP = UnwrapArrayFlip::class;

    final public const ARRAY_UNWRAP_ARRAY_INTERSECT = UnwrapArrayIntersect::class;

    final public const ARRAY_UNWRAP_ARRAY_INTERSECT_ASSOC = UnwrapArrayIntersectAssoc::class;

    final public const ARRAY_UNWRAP_ARRAY_INTERSECT_KEY = UnwrapArrayIntersectKey::class;

    final public const ARRAY_UNWRAP_ARRAY_INTERSECT_UASSOC = UnwrapArrayIntersectUassoc::class;

    final public const ARRAY_UNWRAP_ARRAY_INTERSECT_UKEY = UnwrapArrayIntersectUkey::class;

    final public const ARRAY_UNWRAP_ARRAY_KEYS = UnwrapArrayKeys::class;

    final public const ARRAY_UNWRAP_ARRAY_MAP = UnwrapArrayMap::class;

    final public const ARRAY_UNWRAP_ARRAY_MERGE = UnwrapArrayMerge::class;

    final public const ARRAY_UNWRAP_ARRAY_MERGE_RECURSIVE = UnwrapArrayMergeRecursive::class;

    final public const ARRAY_UNWRAP_ARRAY_PAD = UnwrapArrayPad::class;

    final public const ARRAY_UNWRAP_ARRAY_REDUCE = UnwrapArrayReduce::class;

    final public const ARRAY_UNWRAP_ARRAY_REPLACE = UnwrapArrayReplace::class;

    final public const ARRAY_UNWRAP_ARRAY_REPLACE_RECURSIVE = UnwrapArrayReplaceRecursive::class;

    final public const ARRAY_UNWRAP_ARRAY_REVERSE = UnwrapArrayReverse::class;

    final public const ARRAY_UNWRAP_ARRAY_SLICE = UnwrapArraySlice::class;

    final public const ARRAY_UNWRAP_ARRAY_SPLICE = UnwrapArraySplice::class;

    final public const ARRAY_UNWRAP_ARRAY_UDIFF = UnwrapArrayUdiff::class;

    final public const ARRAY_UNWRAP_ARRAY_UDIFF_ASSOC = UnwrapArrayUdiffAssoc::class;

    final public const ARRAY_UNWRAP_ARRAY_UDIFF_UASSOC = UnwrapArrayUdiffUassoc::class;

    final public const ARRAY_UNWRAP_ARRAY_UINTERSECT = UnwrapArrayUintersect::class;

    final public const ARRAY_UNWRAP_ARRAY_UINTERSECT_ASSOC = UnwrapArrayUintersectAssoc::class;

    final public const ARRAY_UNWRAP_ARRAY_UINTERSECT_UASSOC = UnwrapArrayUintersectUassoc::class;

    final public const ARRAY_UNWRAP_ARRAY_UNIQUE = UnwrapArrayUnique::class;

    final public const ARRAY_UNWRAP_ARRAY_VALUES = UnwrapArrayValues::class;

    /** Assignments */
    final public const ASSIGNMENTS_BITWISE_AND_TO_BITWISE_OR = BitwiseAndToBitwiseOr::class;

    final public const ASSIGNMENTS_BITWISE_OR_TO_BITWISE_AND = BitwiseOrToBitwiseAnd::class;

    final public const ASSIGNMENTS_BITWISE_XOR_TO_BITWISE_AND = BitwiseXorToBitwiseAnd::class;

    final public const ASSIGNMENTS_COALESCE_EQUAL_TO_EQUAL = CoalesceEqualToEqual::class;

    final public const ASSIGNMENTS_CONCAT_EQUAL_TO_EQUAL = ConcatEqualToEqual::class;

    final public const ASSIGNMENTS_DIVIDE_EQUAL_TO_MULTIPLY_EQUAL = DivideEqualToMultiplyEqual::class;

    final public const ASSIGNMENTS_MINUS_EQUAL_TO_PLUS_EQUAL = MinusEqualToPlusEqual::class;

    final public const ASSIGNMENTS_MODULUS_EQUAL_TO_MULTIPLY_EQUAL = ModulusEqualToMultiplyEqual::class;

    final public const ASSIGNMENTS_MULTIPLY_EQUAL_TO_DIVIDE_EQUAL = MultiplyEqualToDivideEqual::class;

    final public const ASSIGNMENTS_PLUS_EQUAL_TO_MINUS_EQUAL = PlusEqualToMinusEqual::class;

    final public const ASSIGNMENTS_POWER_EQUAL_TO_MULTIPLY_EQUAL = PowerEqualToMultiplyEqual::class;

    final public const ASSIGNMENTS_SHIFT_LEFT_TO_SHIFT_RIGHT = ShiftLeftToShiftRight::class;

    final public const ASSIGNMENTS_SHIFT_RIGHT_TO_SHIFT_LEFT = ShiftRightToShiftLeft::class;

    /** Casting */
    final public const CASTING_REMOVE_ARRAY_CAST = RemoveArrayCast::class;

    final public const CASTING_REMOVE_BOOLEAN_CAST = RemoveBooleanCast::class;

    final public const CASTING_REMOVE_DOUBLE_CAST = RemoveDoubleCast::class;

    final public const CASTING_REMOVE_INTEGER_CAST = RemoveIntegerCast::class;

    final public const CASTING_REMOVE_OBJECT_CAST = RemoveObjectCast::class;

    final public const CASTING_REMOVE_STRING_CAST = RemoveStringCast::class;

    /** ControlStructures */
    final public const CONTROL_STRUCTURES_IF_NEGATED = IfNegated::class;

    final public const CONTROL_STRUCTURES_ELSE_IF_NEGATED = ElseIfNegated::class;

    final public const CONTROL_STRUCTURES_TERNARY_NEGATED = TernaryNegated::class;

    final public const CONTROL_STRUCTURES_FOR_ALWAYS_FALSE = ForAlwaysFalse::class;

    final public const CONTROL_STRUCTURES_FOREACH_EMPTY_ITERABLE = ForeachEmptyIterable::class;

    final public const CONTROL_STRUCTURES_WHILE_ALWAYS_FALSE = WhileAlwaysFalse::class;

    final public const CONTROL_STRUCTURES_DO_WHILE_ALWAYS_FALSE = DoWhileAlwaysFalse::class;

    final public const CONTROL_STRUCTURES_BREAK_TO_CONTINUE = BreakToContinue::class;

    final public const CONTROL_STRUCTURES_CONTINUE_TO_BREAK = ContinueToBreak::class;

    /** Equality */
    final public const EQUALITY_EQUAL_TO_NOT_EQUAL = EqualToNotEqual::class;

    final public const EQUALITY_NOT_EQUAL_TO_EQUAL = NotEqualToEqual::class;

    final public const EQUALITY_IDENTICAL_TO_NOT_IDENTICAL = IdenticalToNotIdentical::class;

    final public const EQUALITY_NOT_IDENTICAL_TO_IDENTICAL = NotIdenticalToIdentical::class;

    final public const EQUALITY_GREATER_TO_GREATER_OR_EQUAL = GreaterToGreaterOrEqual::class;

    final public const EQUALITY_GREATER_TO_SMALLER_OR_EQUAL = GreaterToSmallerOrEqual::class;

    final public const EQUALITY_GREATER_OR_EQUAL_TO_GREATER = GreaterOrEqualToGreater::class;

    final public const EQUALITY_GREATER_OR_EQUAL_TO_SMALLER = GreaterOrEqualToSmaller::class;

    final public const EQUALITY_SMALLER_TO_SMALLER_OR_EQUAL = SmallerToSmallerOrEqual::class;

    final public const EQUALITY_SMALLER_TO_GREATER_OR_EQUAL = SmallerToGreaterOrEqual::class;

    final public const EQUALITY_SMALLER_OR_EQUAL_TO_SMALLER = SmallerOrEqualToSmaller::class;

    final public const EQUALITY_SMALLER_OR_EQUAL_TO_GREATER = SmallerOrEqualToGreater::class;

    final public const EQUALITY_EQUAL_TO_IDENTICAL = EqualToIdentical::class;

    final public const EQUALITY_IDENTICAL_TO_EQUAL = IdenticalToEqual::class;

    final public const EQUALITY_NOT_EQUAL_TO_NOT_IDENTICAL = NotEqualToNotIdentical::class;

    final public const EQUALITY_NOT_IDENTICAL_TO_NOT_EQUAL = NotIdenticalToNotEqual::class;

    final public const EQUALITY_SPACESHIP_SWITCH_SIDES = SpaceshipSwitchSides::class;

    /** Logical */
    final public const LOGICAL_BOOLEAN_AND_TO_BOOLEAN_OR = BooleanAndToBooleanOr::class;

    final public const LOGICAL_BOOLEAN_OR_TO_BOOLEAN_AND = BooleanOrToBooleanAnd::class;

    final public const LOGICAL_COALESCE_REMOVE_LEFT = CoalesceRemoveLeft::class;

    final public const LOGICAL_LOGICAL_AND_TO_LOGICAL_OR = LogicalAndToLogicalOr::class;

    final public const LOGICAL_LOGICAL_OR_TO_LOGICAL_AND = LogicalOrToLogicalAnd::class;

    final public const LOGICAL_LOGICAL_XOR_TO_LOGICAL_AND = LogicalXorToLogicalAnd::class;

    final public const LOGICAL_FALSE_TO_TRUE = FalseToTrue::class;

    final public const LOGICAL_TRUE_TO_FALSE = TrueToFalse::class;

    final public const LOGICAL_INSTANCE_OF_TO_TRUE = InstanceOfToTrue::class;

    final public const LOGICAL_INSTANCE_OF_TO_FALSE = InstanceOfToFalse::class;

    final public const LOGICAL_REMOVE_NOT = RemoveNot::class;

    /** Math */
    final public const MATH_MIN_TO_MAX = MinToMax::class;

    final public const MATH_MAX_TO_MIN = MaxToMin::class;

    final public const MATH_ROUND_TO_FLOOR = RoundToFloor::class;

    final public const MATH_ROUND_TO_CEIL = RoundToCeil::class;

    final public const MATH_FLOOR_TO_ROUND = FloorToRound::class;

    final public const MATH_FLOOR_TO_CIEL = FloorToCiel::class;

    final public const MATH_CIEL_TO_FLOOR = CeilToFloor::class;

    final public const MATH_CIEL_TO_ROUND = CeilToRound::class;

    /** Number */
    final public const NUMBER_DECREMENT_FLOAT = DecrementFloat::class;

    final public const NUMBER_INCREMENT_FLOAT = IncrementFloat::class;

    final public const NUMBER_DECREMENT_INTEGER = DecrementInteger::class;

    final public const NUMBER_INCREMENT_INTEGER = IncrementInteger::class;

    /** Removal */
    final public const REMOVAL_REMOVE_ARRAY_ITEM = RemoveArrayItem::class;

    final public const REMOVAL_REMOVE_EARLY_RETURN = RemoveEarlyReturn::class;

    final public const REMOVAL_REMOVE_FUNCTION_CALL = RemoveFunctionCall::class;

    final public const REMOVAL_REMOVE_METHOD_CALL = RemoveMethodCall::class;

    final public const REMOVAL_REMOVE_NULL_SAFE_OPERATOR = RemoveNullSafeOperator::class;

    /** Return */
    final public const RETURN_ALWAYS_RETURN_EMPTY_ARRAY = AlwaysReturnEmptyArray::class;

    final public const RETURN_ALWAYS_RETURN_NULL = AlwaysReturnNull::class;

    /** String */
    final public const STRING_CONCAT_REMOVE_LEFT = ConcatRemoveLeft::class;

    final public const STRING_CONCAT_REMOVE_RIGHT = ConcatRemoveRight::class;

    final public const STRING_CONCAT_SWITCH_SIDES = ConcatSwitchSides::class;

    final public const STRING_EMPTY_STRING_TO_NOT_EMPTY = EmptyStringToNotEmpty::class;

    final public const STRING_NOT_EMPTY_STRING_TO_EMPTY = NotEmptyStringToEmpty::class;

    final public const STRING_STR_STARTS_WITH_TO_STRING_ENDS_WITH = StrStartsWithToStrEndsWith::class;

    final public const STRING_STR_ENDS_WITH_TO_STRING_STARTS_WITH = StrEndsWithToStrStartsWith::class;

    final public const STRING_UNWRAP_CHOP = UnwrapChop::class;

    final public const STRING_UNWRAP_CHUNK_SPLIT = UnwrapChunkSplit::class;

    final public const STRING_UNWRAP_HTML_ENTITIES = UnwrapHtmlentities::class;

    final public const STRING_UNWRAP_HTML_ENTITY_DECODE = UnwrapHtmlEntityDecode::class;

    final public const STRING_UNWRAP_HTML_SPECIALCHARS = UnwrapHtmlspecialchars::class;

    final public const STRING_UNWRAP_HTML_SPECIALCHARS_DECODE = UnwrapHtmlspecialcharsDecode::class;

    final public const STRING_UNWRAP_LCFIRST = UnwrapLcfirst::class;

    final public const STRING_UNWRAP_LTRIM = UnwrapLtrim::class;

    final public const STRING_UNWRAP_MD5 = UnwrapMd5::class;

    final public const STRING_UNWRAP_NL2BR = UnwrapNl2br::class;

    final public const STRING_UNWRAP_RTRIM = UnwrapRtrim::class;

    final public const STRING_UNWRAP_STRIP_TAGS = UnwrapStripTags::class;

    final public const STRING_UNWRAP_STR_IREPLACE = UnwrapStrIreplace::class;

    final public const STRING_UNWRAP_STR_PAD = UnwrapStrPad::class;

    final public const STRING_UNWRAP_STR_REPEAT = UnwrapStrRepeat::class;

    final public const STRING_UNWRAP_STR_REPLACE = UnwrapStrReplace::class;

    final public const STRING_UNWRAP_STRREV = UnwrapStrrev::class;

    final public const STRING_UNWRAP_STR_SHUFFLE = UnwrapStrShuffle::class;

    final public const STRING_UNWRAP_STRTOLOWER = UnwrapStrtolower::class;

    final public const STRING_UNWRAP_STRTOUPPER = UnwrapStrtoupper::class;

    final public const STRING_UNWRAP_SUBSTR = UnwrapSubstr::class;

    final public const STRING_UNWRAP_TRIM = UnwrapTrim::class;

    final public const STRING_UNWRAP_UCFIRST = UnwrapUcfirst::class;

    final public const STRING_UNWRAP_UCWORDS = UnwrapUcwords::class;

    final public const STRING_UNWRAP_WORDWRAP = UnwrapWordwrap::class;

    /** Visibility */
    final public const VISIBILITY_CONSTANT_PUBLIC_TO_PROTECTED = ConstantPublicToProtected::class;

    final public const VISIBILITY_CONSTANT_PROTECTED_TO_PRIVATE = ConstantProtectedToPrivate::class;

    final public const VISIBILITY_FUNCTION_PUBLIC_TO_PROTECTED = FunctionPublicToProtected::class;

    final public const VISIBILITY_FUNCTION_PROTECTED_TO_PRIVATE = FunctionProtectedToPrivate::class;

    final public const VISIBILITY_PROPERTY_PUBLIC_TO_PROTECTED = PropertyPublicToProtected::class;

    final public const VISIBILITY_PROPERTY_PROTECTED_TO_PRIVATE = PropertyProtectedToPrivate::class;

    /** Laravel */
    final public const LARAVEL_UNWRAP_STR_UPPER = LaravelUnwrapStrUpper::class;

    final public const LARAVEL_REMOVE_STRINGABLE_UPPER = LaravelRemoveStringableUpper::class;
}
