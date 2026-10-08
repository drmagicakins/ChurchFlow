<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/*
 | Public marketing pages.
 |
 | The previous version of this controller returned `view('pages.marketing.home')`
 | from all eleven methods — so /features, /pricing, /about, /demo, /contact,
 | /support, /resources/blog, /resources/help-center, /resources/guides and both
 | legal pages all rendered the home page. Every nav link and footer link landed
 | on the same content, which is a worse failure than a 404: the visitor believes
 | they navigated and silently gets the page they were already on.
 |
 | Each method now returns its own view, and the SEO title/description is passed
 | per page so no two pages share a title tag or a meta description.
 |
 | The post-registration journey (section 32 of the brief) is intended to be:
 |   /register -> /billing/plans -> /billing/checkout -> /billing/payment
 |   -> /billing/verification -> /church/setup -> /dashboard
 |
 | That billing engine does not exist yet. Rather than fake it, the pricing page
 | forwards the chosen plan as a query parameter and the pages say plainly that
 | payment is the next step. The integration point is isolated in
 | MarketingSignupController below, so wiring it up later is one method.
 */

class HomeController extends Controller
{
    public function index(): View
    {
        return view('pages.marketing.home', [
            'title' => 'ChurchFlow — The Operating Platform for Modern Churches',
            'description' => 'ChurchFlow is an all-in-one church management platform for members, finance, events, attendance, communication, pastoral care, subvention and reporting. Manage. Connect. Grow.',
        ]);
    }

    public function features(): View
    {
        return view('pages.marketing.features', [
            'title' => 'Features — ChurchFlow',
            'description' => 'Explore every ChurchFlow module: members, finance, subvention, events, attendance, communication, pastoral care and reporting. See what each one does.',
        ]);
    }

    public function pricing(): View
    {
        return view('pages.marketing.pricing', [
            'title' => 'Pricing — ChurchFlow',
            'description' => 'ChurchFlow plans for churches of every size. Email notifications included in every plan; bulk SMS billed separately, pay-as-you-go. Your church is created after verified payment.',
        ]);
    }

    public function about(): View
    {
        return view('pages.marketing.about', [
            'title' => 'About — ChurchFlow',
            'description' => 'Why ChurchFlow exists: church administration should be organised, transparent and secure, so church leaders can spend their time on people rather than paperwork.',
        ]);
    }

    public function demo(): View
    {
        return view('pages.marketing.demo', [
            'title' => 'Product Demo — ChurchFlow',
            'description' => 'Walk through the ChurchFlow interface module by module: dashboard, members, finance, subvention, events, attendance, communication, pastoral care and reports.',
        ]);
    }

    public function contact(): View
    {
        return view('pages.marketing.contact', [
            'title' => 'Contact — ChurchFlow',
            'description' => 'Talk to the ChurchFlow team about your church, your denomination or a multi-branch rollout.',
        ]);
    }

    public function support(): View
    {
        return view('pages.marketing.support', [
            'title' => 'Support — ChurchFlow',
            'description' => 'Get help with ChurchFlow: setup, billing, SMS credit, data import and account questions.',
        ]);
    }

    public function blog(): View
    {
        return view('pages.marketing.resources.blog', [
            'title' => 'Blog — ChurchFlow',
            'description' => 'Notes on church administration, giving, attendance, communication and running a multi-branch church well.',
        ]);
    }

    public function helpCenter(): View
    {
        return view('pages.marketing.resources.help-center', [
            'title' => 'Help Center — ChurchFlow',
            'description' => 'Answers to the questions churches ask most about setting up ChurchFlow, billing, SMS credit and managing members.',
        ]);
    }

    public function guides(): View
    {
        return view('pages.marketing.resources.guides', [
            'title' => 'Guides — ChurchFlow',
            'description' => 'Step-by-step guides for setting up ChurchFlow, importing members, running attendance and configuring subvention rules.',
        ]);
    }

    public function privacy(): View
    {
        return view('pages.marketing.legal.privacy', [
            'title' => 'Privacy Policy — ChurchFlow',
            'description' => 'How ChurchFlow collects, stores, isolates and protects church and member data.',
        ]);
    }

    public function terms(): View
    {
        return view('pages.marketing.legal.terms', [
            'title' => 'Terms of Service — ChurchFlow',
            'description' => 'The terms governing use of the ChurchFlow church management platform.',
        ]);
    }
}
