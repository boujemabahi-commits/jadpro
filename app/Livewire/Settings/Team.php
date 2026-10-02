<?php

namespace App\Livewire\Settings;

use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The center's staff accounts, managed by its owner alone. Every User query
 * here is tenant-scoped by BelongsToTenant, and new accounts are stamped with
 * the owner's own tenant_id — never anything sent by the client.
 */
class Team extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    // Form fields
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public $role_id = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            // Required when creating; optional (leave blank to keep) when editing.
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'role_id' => ['required', Rule::in($this->assignableRoles()->pluck('id')->all())],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => __('الاسم الكامل'),
            'email' => __('البريد الإلكتروني'),
            'password' => __('كلمة المرور'),
            'role_id' => __('الدور'),
        ];
    }

    protected function messages(): array
    {
        return ['role_id.in' => __('الدور المختار غير متاح لهذا المركز.')];
    }

    public function mount(): void
    {
        $this->authorizeManage();
    }

    /** Owner-only, re-checked on every action (the panel is embedded in the open /settings page). */
    protected function authorizeManage(): void
    {
        abort_unless(auth()->user()->can('manage-users'), 403);
    }

    /** Roles an owner may hand out: built-in staff roles + this center's custom roles. Never the owner role. */
    protected function assignableRoles()
    {
        return Role::assignable(auth()->user()->tenant_id)->orderBy('tenant_id')->orderBy('id')->get();
    }

    /** Owners (including yourself) are managed from the Account tab, not here. */
    protected function isEditable(User $user): bool
    {
        return $user->id !== auth()->id() && ! $user->isOwner();
    }

    public function openCreate(): void
    {
        $this->authorizeManage();
        $this->resetForm();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorizeManage();
        $user = User::findOrFail($id);
        abort_unless($this->isEditable($user), 403);

        $this->resetForm();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role_id = (string) ($user->roles->first()?->id ?? '');
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
        $this->resetValidation();
    }

    protected function resetForm(): void
    {
        $this->reset(['name', 'email', 'password', 'role_id']);
    }

    public function save(): void
    {
        $this->authorizeManage();
        $this->name = trim($this->name);
        $this->email = mb_strtolower(trim($this->email));
        $data = $this->validate();
        $role = Role::findOrFail((int) $data['role_id']);

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            abort_unless($this->isEditable($user), 403);

            $user->name = $data['name'];
            $user->email = $data['email'];
            if ($data['password'] !== null && $data['password'] !== '') {
                $user->password = $data['password'];
            }
            $user->save();
            $user->syncRoles([$role]);

            $this->dispatch('toast', message: __('تم تحديث بيانات المستخدم بنجاح'));
        } else {
            $user = User::create([
                // Security-critical: the new account belongs to the creating owner's center.
                'tenant_id' => auth()->user()->tenant_id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => 'نشط',
                'email_verified_at' => now(),
            ]);
            $user->syncRoles([$role]);

            $this->dispatch('toast', message: __('تمت إضافة المستخدم بنجاح'));
        }

        $this->closeModal();
    }

    /** نشط ⇄ متوقف. A paused account is signed out on its next request and can't log in. */
    public function toggleStatus(int $id): void
    {
        $this->authorizeManage();
        $user = User::findOrFail($id);
        abort_unless($this->isEditable($user), 403);

        $user->status = $user->isActive() ? 'متوقف' : 'نشط';
        $user->save();

        $this->dispatch('toast', message: $user->isActive() ? __('تم تفعيل الحساب') : __('تم إيقاف الحساب'));
    }

    public function render()
    {
        $users = User::with('roles')->orderBy('name')->get();

        return view('livewire.settings.team', [
            'users' => $users,
            'roles' => $this->assignableRoles(),
            'editableIds' => $users->filter(fn (User $u) => $this->isEditable($u))->pluck('id')->all(),
        ]);
    }
}
