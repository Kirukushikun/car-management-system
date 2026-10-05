<?php

namespace App\Enums;

/**
 * Function-based roles, named for what each person does in the CAR workflow
 * rather than their job title (see development plan, status update).
 */
enum Role: string
{
    case Requestor = 'requestor';
    case RequestorApprover = 'requestor_approver';
    case Responder = 'responder';
    case ResponderApprover = 'responder_approver';
    case Monitor = 'monitor';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Requestor => 'Requestor',
            self::RequestorApprover => 'Requestor Approver',
            self::Responder => 'Responder',
            self::ResponderApprover => 'Responder Approver',
            self::Monitor => 'Monitor',
            self::Admin => 'IT Admin',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Requestor => 'Files the CAR for the customer or complainant (Phase I) and sends it for release. Fixes anything that comes back.',
            self::RequestorApprover => 'Approves or rejects a CAR before it is released to the Responder, and gives the final acceptance that closes it.',
            self::Responder => 'Enters the containment, root cause and corrective action (Phase II), then uploads the implementation evidence (Phase III).',
            self::ResponderApprover => 'Approves the root cause and corrective action, then validates whether the implemented action was effective.',
            self::Monitor => 'Read-only dashboard — repeat offenses, open/closed, response and resolution time, frequency of CAR issuance.',
            self::Admin => 'Maintains users, roles and the category / deadline matrix. Read-only access to every CAR for support.',
        };
    }

    /**
     * Color token used for this role's avatar and pills (matches the mockup's CSS variables).
     */
    public function tone(): string
    {
        return match ($this) {
            self::Requestor => 'green',
            self::RequestorApprover => 'violet',
            self::Responder => 'blue',
            self::ResponderApprover => 'amber',
            self::Monitor => 'accent',
            self::Admin => 'slate',
        };
    }

    /**
     * Responder roles belong to one farm and only act on that farm's CARs.
     */
    public function isFarmScoped(): bool
    {
        return in_array($this, [self::Responder, self::ResponderApprover], true);
    }

    /**
     * The role allowed to act as this role's approver in the approver chain (Step 10).
     * Approver roles escalate to another approver of the same kind; Monitor and Admin have none.
     */
    public function approverRole(): ?self
    {
        return match ($this) {
            self::Requestor, self::RequestorApprover => self::RequestorApprover,
            self::Responder, self::ResponderApprover => self::ResponderApprover,
            self::Monitor, self::Admin => null,
        };
    }

    public function canViewDashboard(): bool
    {
        return $this === self::Monitor;
    }

    public function canCreateCars(): bool
    {
        return $this === self::Requestor;
    }

    public function canViewQueue(): bool
    {
        return in_array($this, [self::Requestor, self::RequestorApprover, self::Responder, self::ResponderApprover], true);
    }

    public function canViewOverdue(): bool
    {
        return in_array($this, [self::ResponderApprover, self::Monitor, self::Admin], true);
    }

    public function canAdminister(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Sidebar items for this role, in display order. The first item is the role's home page.
     *
     * @return list<array{label: string, route: string, params: array<string, string>, icon: string, count: ?string}>
     */
    public function navigation(): array
    {
        $queueLabel = in_array($this, [self::RequestorApprover, self::ResponderApprover], true) ? 'My Approvals' : 'My Queue';

        $queue = ['label' => $queueLabel, 'route' => 'cars.index', 'params' => ['view' => 'mine'], 'icon' => 'inbox', 'count' => 'mine'];
        $all = ['label' => 'All CARs', 'route' => 'cars.index', 'params' => [], 'icon' => 'list', 'count' => 'all'];
        $overdue = ['label' => 'Overdue', 'route' => 'cars.index', 'params' => ['view' => 'overdue'], 'icon' => 'alert', 'count' => 'overdue'];
        $create = ['label' => 'New CAR', 'route' => 'cars.create', 'params' => [], 'icon' => 'plus', 'count' => null];
        $dashboard = ['label' => 'Dashboard', 'route' => 'dashboard', 'params' => [], 'icon' => 'dashboard', 'count' => null];
        $users = ['label' => 'Users & Roles', 'route' => 'admin.users', 'params' => [], 'icon' => 'users', 'count' => null];
        $matrix = ['label' => 'Category Matrix', 'route' => 'admin.matrix', 'params' => [], 'icon' => 'sliders', 'count' => null];

        return match ($this) {
            self::Requestor => [$queue, $create, $all],
            self::RequestorApprover, self::Responder => [$queue, $all],
            self::ResponderApprover => [$queue, $all, $overdue],
            self::Monitor => [$dashboard, $all, $overdue],
            self::Admin => [$users, $matrix, $all, $overdue],
        };
    }

    public function homeUrl(): string
    {
        $home = $this->navigation()[0];

        return route($home['route'], $home['params']);
    }
}
