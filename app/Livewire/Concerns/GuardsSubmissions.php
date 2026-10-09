<?php

namespace App\Livewire\Concerns;

use App\Models\Attachment;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * The fence around every CAR submission: the change and its files are saved all-or-nothing, and
 * an unexpected failure (storage, database, a crashed service) becomes a message on the form —
 * nothing is submitted, nobody is notified, and the user's entries stay so they can try again.
 * Validation and permission errors pass through and show as usual.
 */
trait GuardsSubmissions
{
    public const SUBMISSION_FAILED = 'Something went wrong while saving, so nothing was submitted. Your entries are still here — please try again. If it keeps happening, contact the IT Admin.';

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $work
     * @return TResult|null null when the submission failed and the message was shown
     */
    protected function guardSubmission(Closure $work): mixed
    {
        try {
            return Attachment::atomically($work);
        } catch (ValidationException|AuthorizationException|HttpExceptionInterface $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            $this->addError('submission', self::SUBMISSION_FAILED);

            return null;
        }
    }
}
