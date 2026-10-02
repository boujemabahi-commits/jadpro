<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

/** The signed-in user's own account: name, email, and an optional password change. */
class Account extends Component
{
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore(auth()->id())],
            // Changing the password or the sign-in email requires proving the current
            // password: an open session left on a shared computer must not be enough
            // to take the account over (new email → password reset).
            'current_password' => [
                Rule::requiredIf(fn () => $this->password !== '' || mb_strtolower(trim($this->email)) !== auth()->user()->email),
                'nullable',
                'current_password',
            ],
            'password' => ['nullable', 'string', 'min:8', 'max:255', 'confirmed'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => __('الاسم الكامل'),
            'email' => __('البريد الإلكتروني'),
            'current_password' => __('كلمة المرور الحالية'),
            'password' => __('كلمة المرور الجديدة'),
            'password_confirmation' => __('تأكيد كلمة المرور'),
        ];
    }

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function save(): void
    {
        $this->email = mb_strtolower(trim($this->email));
        $data = $this->validate();

        $user = auth()->user();
        $user->name = $data['name'];
        $user->email = $data['email'];

        if ($data['password'] !== null && $data['password'] !== '') {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->dispatch('toast', message: __('تم حفظ بيانات الحساب بنجاح'));
    }

    public function render()
    {
        return view('livewire.settings.account');
    }
}
