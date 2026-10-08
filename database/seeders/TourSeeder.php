<?php

namespace Database\Seeders;

use App\Models\Tour;
use Illuminate\Database\Seeder;

class TourSeeder extends Seeder
{
    public function run(): void
    {
        $welcome = Tour::updateOrCreate(
            ['slug' => 'welcome'],
            ['name' => 'Welcome to your Church Dashboard', 'feature' => null, 'version' => 1, 'is_active' => true],
        );

        $welcome->steps()->delete();
        $welcome->steps()->createMany([
            ['title' => 'This is your dashboard.', 'description' => 'Everything about your church, in one place.', 'target_selector' => '#dashboard', 'sort_order' => 1],
            ['title' => 'Monitor church activity', 'description' => 'Here you can monitor your church activity.', 'target_selector' => '#activity-feed', 'sort_order' => 2],
            ['title' => 'Members', 'description' => 'Use Members to manage your church community.', 'target_selector' => 'a[href*="members"]', 'sort_order' => 3],
            ['title' => 'Finance', 'description' => 'Finance gives you a complete view of church finances.', 'target_selector' => 'a[href*="finance"]', 'sort_order' => 4],
            ['title' => 'Events', 'description' => 'Events helps you organize programs.', 'target_selector' => 'a[href*="events"]', 'sort_order' => 5],
            ['title' => 'Reports', 'description' => 'Reports helps you understand what is happening.', 'target_selector' => 'a[href*="reports"]', 'sort_order' => 6],
        ]);

        // §39: product tour also explains billing — the brief's own
        // example. Shown as a separate, feature-keyed tour (not bolted
        // onto 'welcome') so it can be re-triggered independently.
        $billing = Tour::updateOrCreate(
            ['slug' => 'billing-basics'],
            ['name' => 'Your subscription and SMS credits', 'feature' => 'billing', 'version' => 1, 'is_active' => true],
        );

        $billing->steps()->delete();
        $billing->steps()->createMany([
            ['title' => 'Your subscription', 'description' => 'This shows your current plan and next billing date.', 'target_selector' => '#subscription-card', 'sort_order' => 1],
            ['title' => 'SMS Wallet', 'description' => 'Bulk SMS uses separate SMS credits. Email notifications are included with your subscription.', 'target_selector' => '#sms-wallet-card', 'sort_order' => 2],
            ['title' => 'Buy SMS', 'description' => 'Purchase SMS credits whenever your church needs to send bulk messages.', 'target_selector' => '#buy-sms-button', 'sort_order' => 3],
        ]);

        // §30: an example "✨ New Feature" tour — feature-keyed so a future
        // release can gate it behind the matching feature flag.
        $budgeting = Tour::updateOrCreate(
            ['slug' => 'feature-budgeting'],
            ['name' => 'New Feature: Church Budgeting', 'feature' => 'budgeting', 'version' => 1, 'is_active' => true],
        );

        $budgeting->steps()->delete();
        $budgeting->steps()->createMany([
            ['title' => 'Budget dashboard', 'description' => 'See all your budgets at a glance.', 'target_selector' => '#budgets-index', 'sort_order' => 1],
            ['title' => 'Create budget', 'description' => 'Set up a budget for any period.', 'target_selector' => '#create-budget-button', 'sort_order' => 2],
            ['title' => 'Add budget categories', 'description' => 'Break your budget down by category.', 'target_selector' => '#budget-items-form', 'sort_order' => 3],
            ['title' => 'Compare budget vs actual', 'description' => 'See how actual spend compares to plan.', 'target_selector' => '#budget-variance-table', 'sort_order' => 4],
            ['title' => 'Generate report', 'description' => 'Export your budget comparison as a report.', 'target_selector' => '#export-budget-button', 'sort_order' => 5],
        ]);
    }
}
