<?php

namespace App\Providers;

use App\Models\ChangeOrder;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\ContractLetter;
use App\Models\ContractualEvent;
use App\Models\User;
use App\Policies\ChangeOrderPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\ContractPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\EventPolicy;
use App\Policies\LetterPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Super admin bypasses todas las policies
        Gate::before(function (User $user, string $ability) {
            if ($user->is_super_admin) {
                return true;
            }
        });

        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Contract::class, ContractPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ContractualEvent::class, EventPolicy::class);
        Gate::policy(ContractLetter::class, LetterPolicy::class);
        Gate::policy(ChangeOrder::class, ChangeOrderPolicy::class);
        Gate::policy(ContractDocument::class, DocumentPolicy::class);
    }
}
