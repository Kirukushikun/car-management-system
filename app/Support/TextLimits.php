<?php

namespace App\Support;

/**
 * Maximum lengths for every text a user types into a CAR, in one place. The forms validate with
 * these, the inputs carry them as maxlength (which drives the live character counter), and the
 * workflow checks the decision reason against them as well.
 */
final class TextLimits
{
    /**
     * Names and short answers: issued by, complainant, responsible persons.
     */
    public const SHORT = 150;

    /**
     * Details of the problem.
     */
    public const PROBLEM = 3000;

    /**
     * Other long answers: initial action, containment, root cause, corrective actions, evidence notes.
     */
    public const LONG = 2000;

    /**
     * The reason or remarks given with a decision (reject, return, not effective, not accepted, void).
     */
    public const REASON = 1000;
}
