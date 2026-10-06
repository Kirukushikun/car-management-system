<?php

namespace App\Observers;

use App\Models\Audit;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Records every create and update of the models it observes (users, categories, sub-categories)
 * in the audit log. Passwords and tokens are never stored — only that they changed.
 */
class AuditObserver
{
    /**
     * Attributes whose values must never be written to the log.
     *
     * @var list<string>
     */
    private const SECRET = ['password', 'remember_token'];

    /**
     * Bookkeeping columns not worth logging.
     *
     * @var list<string>
     */
    private const IGNORED = ['created_at', 'updated_at', 'email_verified_at'];

    public function created(Model $model): void
    {
        $this->record($model, 'created', null, $this->clean($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = $this->clean($model->getChanges());

        if ($changes === []) {
            return;
        }

        $this->record($model, 'updated', array_intersect_key($this->clean($model->getOriginal()), $changes), $changes);
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>  $new
     */
    private function record(Model $model, string $event, ?array $old, array $new): void
    {
        Audit::create([
            'user_id' => auth()->id(),
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function clean(array $values): array
    {
        $values = array_diff_key($values, array_flip(self::IGNORED));

        foreach (self::SECRET as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '(changed)';
            }
        }

        return array_map(fn (mixed $value): mixed => $value instanceof BackedEnum ? $value->value : $value, $values);
    }
}
