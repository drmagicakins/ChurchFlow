<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>ChurchFlow — Run your whole church on one platform</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#1B2340; --ink-soft:#4A5072; --paper:#F6F4EF; --paper-raised:#FFFFFF;
    --gold:#C8922A; --teal:#2F6E63; --line:#DCD7C9; --brick:#A8472E;
    --radius:3px;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--paper);color:var(--ink);font-family:'Work Sans',system-ui,sans-serif;line-height:1.5;-webkit-font-smoothing:antialiased}
  h1,h2,h3{font-family:'Fraunces',serif;font-weight:500;margin:0;letter-spacing:-0.01em}
  a{color:inherit}
  .wrap{max-width:1180px;margin:0 auto;padding:0 28px}
  .btn{display:inline-block;padding:13px 24px;border-radius:var(--radius);font-weight:600;font-size:15px;text-decoration:none;border:1px solid transparent;cursor:pointer}
  .btn-primary{background:var(--ink);color:var(--paper)}
  .btn-primary:hover{background:var(--ink-soft)}
  .btn-ghost{border-color:var(--line);color:var(--ink)}
  .btn-ghost:hover{border-color:var(--ink-soft)}
  .eyebrow{color:var(--ink-soft);font-size:14px;margin:0 0 10px}

  nav{position:sticky;top:0;background:rgba(246,244,239,.92);backdrop-filter:blur(6px);border-bottom:1px solid var(--line);z-index:20}
  nav .wrap{display:flex;align-items:center;justify-content:space-between;height:68px}
  .logo{font-family:'Fraunces',serif;font-size:21px;font-weight:600}
  .navlinks{display:flex;gap:28px;align-items:center;font-size:15px}
  .navlinks a{text-decoration:none;color:var(--ink-soft)}
  .navlinks a:hover{color:var(--ink)}
  .navcta{display:flex;gap:10px}
  @media (max-width:760px){.navlinks{display:none}}

  .hero{padding:72px 0 56px;border-bottom:1px solid var(--line)}
  .hero-grid{display:grid;grid-template-columns:1fr 1fr;gap:56px;align-items:center}
  @media (max-width:900px){.hero-grid{grid-template-columns:1fr}}
  .hero h1{font-size:clamp(34px,4.6vw,52px);line-height:1.05}
  .hero p.lead{color:var(--ink-soft);font-size:18px;max-width:46ch;margin:20px 0 28px}
  .hero-ctas{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px}
  .hero-note{font-size:13px;color:var(--ink-soft)}

  .explorer{background:var(--paper-raised);border:1px solid var(--line);border-radius:var(--radius);overflow:hidden}
  .tabs{display:flex;flex-wrap:wrap;border-bottom:1px solid var(--line)}
  .tab{flex:1 1 auto;padding:13px 10px;font-size:13px;font-weight:600;text-align:center;background:none;border:none;border-right:1px solid var(--line);color:var(--ink-soft);cursor:pointer;font-family:inherit}
  .tab:last-child{border-right:none}
  .tab[aria-selected="true"]{color:var(--ink);background:var(--paper)}
  .panel{padding:22px}
  .panel h4{font-size:15px;margin-bottom:2px}
  .panel .sub{color:var(--ink-soft);font-size:13px;margin-bottom:16px}
  .stat-row{display:flex;gap:22px;margin-bottom:18px;flex-wrap:wrap}
  .stat .num{font-family:'Fraunces',serif;font-size:26px;display:block}
  .stat .lbl{font-size:12px;color:var(--ink-soft)}
  .bars{display:flex;align-items:flex-end;gap:7px;height:72px;margin-bottom:16px}
  .bars i{flex:1;background:var(--teal);border-radius:2px 2px 0 0;opacity:.85}
  table.mini{width:100%;border-collapse:collapse;font-size:13px}
  table.mini th{text-align:left;color:var(--ink-soft);font-weight:500;font-size:11px;padding-bottom:6px;border-bottom:1px solid var(--line)}
  table.mini td{padding:7px 0;border-bottom:1px solid var(--line)}
  table.mini tr:last-child td{border-bottom:none}
  .tag{display:inline-block;font-size:11px;padding:2px 7px;border-radius:2px;background:var(--line)}
  .tag.ok{background:rgba(47,110,99,.18);color:var(--teal)}
  .tag.warn{background:rgba(200,146,42,.22);color:#8a6414}

  section{padding:64px 0;border-bottom:1px solid var(--line)}
  .section-head{max-width:60ch;margin-bottom:36px}
  .section-head h2{font-size:clamp(26px,3vw,34px)}
  .section-head p{color:var(--ink-soft);font-size:16px;margin-top:10px}

  .compare{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:var(--line);border:1px solid var(--line)}
  .compare div{background:var(--paper-raised);padding:20px 24px}
  .compare h3{font-size:13px;color:var(--ink-soft);font-weight:600;margin-bottom:12px}
  .compare ul{margin:0;padding-left:18px;font-size:15px}
  .compare li{margin-bottom:8px}
  .compare .now li{color:var(--ink-soft)}

  .modlist{border-top:1px solid var(--line)}
  .modrow{display:grid;grid-template-columns:160px 1fr;gap:24px;padding:20px 0;border-bottom:1px solid var(--line)}
  @media (max-width:700px){.modrow{grid-template-columns:1fr}}
  .modrow h3{font-size:17px}
  .modrow p{margin:4px 0 0;color:var(--ink-soft);font-size:14.5px;max-width:62ch}

  .steps{display:grid;grid-template-columns:repeat(4,1fr);gap:0;border:1px solid var(--line)}
  @media (max-width:800px){.steps{grid-template-columns:1fr 1fr}}
  .step{padding:22px;border-right:1px solid var(--line);border-bottom:1px solid var(--line)}
  .step:nth-child(4n){border-right:none}
  .step .n{font-family:'Fraunces',serif;color:var(--gold);font-size:14px;margin-bottom:8px;display:block}
  .step h3{font-size:16px;margin-bottom:6px}
  .step p{margin:0;color:var(--ink-soft);font-size:14px}

  .billing-toggle{display:inline-flex;border:1px solid var(--line);border-radius:999px;padding:3px;margin-bottom:28px}
  .billing-toggle button{border:none;background:none;padding:8px 18px;border-radius:999px;font-family:inherit;font-size:13.5px;font-weight:600;color:var(--ink-soft);cursor:pointer}
  .billing-toggle button[aria-pressed="true"]{background:var(--ink);color:var(--paper)}

  .plans{display:grid;grid-template-columns:repeat({{ max(count($plans), 1) }},1fr);gap:1px;background:var(--line);border:1px solid var(--line)}
  @media (max-width:800px){.plans{grid-template-columns:1fr}}
  .plan{background:var(--paper-raised);padding:28px 24px;display:flex;flex-direction:column}
  .plan.featured{background:var(--ink);color:var(--paper)}
  .plan.featured .ink-soft{color:#C9CCE2}
  .plan h3{font-size:19px}
  .price{font-family:'Fraunces',serif;font-size:36px;margin:14px 0 4px}
  .price span{font-size:14px;font-family:'Work Sans',sans-serif;color:var(--ink-soft)}
  .plan.featured .price span{color:#C9CCE2}
  .plan ul{list-style:none;margin:18px 0 24px;padding:0;font-size:14px;flex:1}
  .plan li{padding:7px 0;border-top:1px solid var(--line)}
  .plan.featured li{border-top:1px solid #333a5c}
  .billing-note{font-size:12.5px;color:var(--ink-soft);margin-top:10px;text-align:center}

  details{border-bottom:1px solid var(--line);padding:16px 0}
  summary{cursor:pointer;font-weight:600;font-size:15.5px;list-style:none;display:flex;justify-content:space-between}
  summary::-webkit-details-marker{display:none}
  summary:after{content:"+";color:var(--ink-soft);font-size:18px}
  details[open] summary:after{content:"–"}
  details p{color:var(--ink-soft);margin:12px 0 0;max-width:68ch;font-size:14.5px}

  .cta-band{background:var(--ink);color:var(--paper);text-align:left;padding:64px 0;border-bottom:none}
  .cta-band .wrap{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:24px}
  .cta-band h2{font-size:28px;color:var(--paper)}
  .cta-band p{color:#C9CCE2;margin-top:8px}
  .cta-band .btn-primary{background:var(--gold);color:var(--ink)}
  .cta-band .btn-ghost{border-color:#454b6e;color:var(--paper)}

  footer{padding:36px 0;font-size:13px;color:var(--ink-soft);display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px}
  footer a{text-decoration:none;color:var(--ink-soft);margin-right:16px}
</style>
</head>
<body>

<nav>
  <div class="wrap">
    <div class="logo">ChurchFlow</div>
    <div class="navlinks">
      <a href="#explorer">Platform</a>
      <a href="#modules">Modules</a>
      <a href="#pricing">Pricing</a>
      <a href="#faq">FAQ</a>
    </div>
    <div class="navcta">
      <a class="btn btn-ghost" href="{{ route('login') }}">Sign in</a>
      <a class="btn btn-primary" href="{{ route('register') }}">Get Started</a>
    </div>
  </div>
</nav>

<header class="hero">
  <div class="wrap hero-grid">
    <div>
      <p class="eyebrow">Church management, built as one platform</p>
      <h1>Run your whole church.<br>One platform, start to finish.</h1>
      <p class="lead">Members, attendance, finance, subvention, events and communication — one record of your church instead of six spreadsheets that disagree with each other.</p>
      <div class="hero-ctas">
        <a class="btn btn-primary" href="{{ route('register') }}">Get Started</a>
        <a class="btn btn-ghost" href="#pricing">See pricing</a>
      </div>
      <p class="hero-note">14-day trial on every plan. No card required to start.</p>
    </div>

    <div class="explorer" id="explorer">
      <div class="tabs" role="tablist" aria-label="Platform preview">
        <button class="tab" role="tab" aria-selected="true">People</button>
        <button class="tab" role="tab" aria-selected="false">Finance</button>
        <button class="tab" role="tab" aria-selected="false">Attendance</button>
        <button class="tab" role="tab" aria-selected="false">Subvention</button>
        <button class="tab" role="tab" aria-selected="false">Comms</button>
      </div>
      <div class="panel" id="panel" aria-live="polite"></div>
    </div>
  </div>
</header>

<section id="problem">
  <div class="wrap">
    <div class="section-head">
      <h2>You're already running a church. You shouldn't also be running six disconnected systems.</h2>
    </div>
    <div class="compare">
      <div class="now">
        <h3>WITHOUT CHURCHFLOW</h3>
        <ul>
          <li>Member records in a notebook, a spreadsheet, and someone's phone</li>
          <li>Subvention worked out by hand each month, formulas copied from last year</li>
          <li>Attendance guessed at, not tracked</li>
          <li>Announcements sent department by department, by hand</li>
        </ul>
      </div>
      <div class="then">
        <h3>WITH CHURCHFLOW</h3>
        <ul>
          <li>One member record, searchable, with family and department linked</li>
          <li>Subvention calculated automatically from your own configured rates</li>
          <li>Attendance taken in minutes, trends visible instantly</li>
          <li>One announcement reaches the right people — email included, SMS when you need it</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<section id="modules">
  <div class="wrap">
    <div class="section-head">
      <h2>Everything your church needs</h2>
      <p>Every module below is part of the same platform — the same member, the same branch, the same login.</p>
    </div>
    <div class="modlist">
      <div class="modrow"><h3>People</h3><p>Members, families, departments and groups, searchable and exportable, with a record for every branch in your organization.</p></div>
      <div class="modrow"><h3>Attendance &amp; Events</h3><p>Take attendance in minutes, run programs with registration and capacity limits, and see trends across branches over time.</p></div>
      <div class="modrow"><h3>Finance</h3><p>Income, expenses, budgets and accounts, with an approval workflow before anything is final — and nothing is ever deleted, only corrected.</p></div>
      <div class="modrow"><h3>Subvention</h3><p>Configure your own remittance rules once; every branch's monthly calculation follows automatically, with a full review and approval trail.</p></div>
      <div class="modrow"><h3>Pastoral Care</h3><p>Prayer requests, counseling and follow-up — visible only to the pastor it's assigned to, never to general administrators.</p></div>
      <div class="modrow"><h3>Communication</h3><p>Email is included with your plan. Bulk SMS is billed separately by credits, with the exact cost shown before you send.</p></div>
      <div class="modrow"><h3>Reports</h3><p>Membership, giving, attendance and subvention reports, ready to export whenever leadership asks.</p></div>
      <div class="modrow"><h3>Multi-branch structure</h3><p>Province, area, diocese, parish — your organization's own terms, configured once, used everywhere in the platform.</p></div>
    </div>
  </div>
</section>

<section id="how">
  <div class="wrap">
    <div class="section-head">
      <h2>How it works</h2>
    </div>
    <div class="steps">
      <div class="step"><span class="n">1</span><h3>Choose a plan</h3><p>Pick the plan that fits your church's size. Every plan includes email and the core modules.</p></div>
      <div class="step"><span class="n">2</span><h3>Set up your church</h3><p>Add your branches, departments and first members — skip anything non-essential and finish it later.</p></div>
      <div class="step"><span class="n">3</span><h3>Invite your team</h3><p>Bring in pastors and administrators with exactly the access their role needs, nothing more.</p></div>
      <div class="step"><span class="n">4</span><h3>Run your church</h3><p>Attendance, finance, subvention and communication, all from the same dashboard from day one.</p></div>
    </div>
  </div>
</section>

<section id="pricing">
  <div class="wrap">
    <div class="section-head">
      <h2>Pricing</h2>
      <p>Email notifications are included with every plan. Bulk SMS is available separately through SMS credits — you always see the cost before you send.</p>
    </div>

    @if($plans->isNotEmpty())
      @if($plans->contains(fn($p) => $p->yearly_price !== null))
        <div class="billing-toggle" role="group" aria-label="Billing interval">
          <button type="button" id="toggle-monthly" aria-pressed="true">Monthly</button>
          <button type="button" id="toggle-yearly" aria-pressed="false">Yearly</button>
        </div>
      @endif

      <div class="plans">
        @foreach($plans as $plan)
          <div class="plan {{ $loop->index === 1 && $plans->count() >= 3 ? 'featured' : '' }}">
            <h3>{{ $plan->name }}</h3>
            <div class="price">
              <span class="price-monthly">{{ $plan->currency === 'NGN' ? '₦' : $plan->currency.' ' }}{{ number_format($plan->monthly_price) }}<span> /month</span></span>
              @if($plan->yearly_price !== null)
                <span class="price-yearly" style="display:none">{{ $plan->currency === 'NGN' ? '₦' : $plan->currency.' ' }}{{ number_format($plan->yearly_price) }}<span> /year</span></span>
              @endif
            </div>
            <ul>
              <li>{{ $plan->max_members ? number_format($plan->max_members).' members' : 'Unlimited members' }}</li>
              <li>{{ $plan->max_branches ? $plan->max_branches.' branch'.($plan->max_branches > 1 ? 'es' : '') : 'Unlimited branches' }} · {{ $plan->max_admins ? $plan->max_admins.' admin logins' : 'Unlimited admin logins' }}</li>
              <li>Finance &amp; subvention included</li>
              @if($plan->hasFeature('advanced_reports'))<li>Advanced reports</li>@endif
              @if($plan->hasFeature('api_access'))<li>API access</li>@endif
              @if($plan->hasFeature('custom_domain'))<li>Custom domain</li>@endif
              <li>Email included</li>
            </ul>
            <a class="btn {{ $loop->index === 1 && $plans->count() >= 3 ? 'btn-primary' : 'btn-ghost' }}"
               href="{{ auth()->check() && !auth()->user()->church_id ? route('checkout.review', $plan) : route('register') }}">
              Choose {{ $plan->name }}
            </a>
          </div>
        @endforeach
      </div>
      <p class="billing-note">SMS credits purchased separately, any plan.</p>
    @else
      <p>Plans are being finalized — <a href="{{ route('register') }}">create an account</a> and we'll notify you the moment pricing is live.</p>
    @endif
  </div>
</section>

<section id="faq">
  <div class="wrap">
    <div class="section-head"><h2>Questions</h2></div>
    <details open>
      <summary>Is SMS really billed separately?</summary>
      <p>Yes. Email is included in every plan at no extra cost. Bulk SMS uses a separate credit balance you top up as needed — you always see the exact unit count and cost before a campaign sends.</p>
    </details>
    <details>
      <summary>Can we use our denomination's own structure and terms?</summary>
      <p>Yes. ChurchFlow doesn't assume any one denomination's hierarchy. You configure your own levels and labels — province, diocese, area, parish, or your own terms — once, during setup.</p>
    </details>
    <details>
      <summary>What happens to our data if we cancel?</summary>
      <p>Nothing is deleted. Access is restricted until you reactivate; your members, finance and subvention history stay intact and exportable.</p>
    </details>
    <details>
      <summary>Can different staff see different things?</summary>
      <p>Yes. Roles and permissions are configurable per church, and pastoral care records are visible only to the assigned pastor — not to general administrators, regardless of their other access.</p>
    </details>
  </div>
</section>

<div class="cta-band">
  <div class="wrap">
    <div>
      <h2>Ready to bring it all into one place?</h2>
      <p>Start your 14-day trial — no card required.</p>
    </div>
    <div class="hero-ctas">
      <a class="btn btn-primary" href="{{ route('register') }}">Get Started</a>
      <a class="btn btn-ghost" href="#modules">See all modules</a>
    </div>
  </div>
</div>

<footer>
  <div class="wrap" style="display:flex;justify-content:space-between;width:100%;flex-wrap:wrap">
    <div>© {{ now()->year }} ChurchFlow. Built for churches of every size.</div>
    <div><a href="#modules">Modules</a><a href="#pricing">Pricing</a><a href="#faq">FAQ</a></div>
  </div>
</footer>

<script>
(function(){
  var data = {
    0: { stats: [["1,284","Members"],["+38","This month"],["6","Branches"]], bars: [40,55,48,62,58,70,66,74],
      cols: ["Member","Branch","Status"],
      rows: [["Ada Chukwu","Lagos Central","<span class=\"tag ok\">Active</span>"],
             ["Emeka Obi","Abuja North","<span class=\"tag ok\">Active</span>"],
             ["Grace Udo","Lagos Central","<span class=\"tag warn\">Visitor</span>"]] },
    1: { stats: [["₦12.4m","Income (mtd)"],["₦7.8m","Expenses"],["₦4.6m","Balance"]], bars: [30,52,41,66,59,77,64,80],
      cols: ["Category","Amount","Status"],
      rows: [["Tithe — Sunday","₦2,100,000","<span class=\"tag ok\">Posted</span>"],
             ["Building fund","₦850,000","<span class=\"tag ok\">Posted</span>"],
             ["Generator repair","₦320,000","<span class=\"tag warn\">Pending approval</span>"]] },
    2: { stats: [["812","Last Sunday"],["71%","Avg. rate"],["4","Sessions this week"]], bars: [60,64,58,70,66,80,78,84],
      cols: ["Service","Date","Present"],
      rows: [["Sunday Service","Sep 28","812"],["Midweek","Oct 1","240"],["Youth Service","Oct 3","196"]] },
    3: { stats: [["₦1.8m","Remittance due"],["12","Branches submitted"],["3","Pending review"]], bars: [45,50,47,60,55,68,62,71],
      cols: ["Branch","Period","Status"],
      rows: [["Lagos Province 02","Sept 2026","<span class=\"tag warn\">Submitted</span>"],
             ["Abuja Province 01","Sept 2026","<span class=\"tag ok\">Approved</span>"],
             ["Ibadan Province 01","Sept 2026","<span class=\"tag\">Draft</span>"]] },
    4: { stats: [["2","Announcements sent"],["4,250","SMS credits left"],["1,284","Reached by email"]], bars: [20,38,30,52,44,60,55,66],
      cols: ["Message","Audience","Channel"],
      rows: [["Service time change","Entire church","Email"],
             ["Youth conference","Youth dept.","<span class=\"tag warn\">SMS · 348 credits</span>"],
             ["Prayer meeting","Workers","Email"]] }
  };

  var tabs = document.querySelectorAll('.tab');
  var panel = document.getElementById('panel');

  function render(i){
    var d = data[i];
    var bars = d.bars.map(function(h){return '<i style="height:'+h+'%"></i>';}).join('');
    var rows = d.rows.map(function(r){return '<tr><td>'+r[0]+'</td><td>'+r[1]+'</td><td>'+r[2]+'</td></tr>';}).join('');
    var stats = d.stats.map(function(s){return '<div class="stat"><span class="num">'+s[0]+'</span><span class="lbl">'+s[1]+'</span></div>';}).join('');
    panel.innerHTML =
      '<h4>'+tabs[i].textContent+' overview</h4><p class="sub">Live snapshot — your actual numbers replace this after setup.</p>'+
      '<div class="stat-row">'+stats+'</div>'+
      '<div class="bars">'+bars+'</div>'+
      '<table class="mini"><tr><th>'+d.cols.join('</th><th>')+'</th></tr>'+rows+'</table>';
  }

  tabs.forEach(function(tab, i){
    tab.addEventListener('click', function(){
      tabs.forEach(function(t){ t.setAttribute('aria-selected','false'); });
      tab.setAttribute('aria-selected','true');
      render(i);
    });
  });

  render(0);

  var mBtn = document.getElementById('toggle-monthly'), yBtn = document.getElementById('toggle-yearly');
  if (mBtn && yBtn) {
    mBtn.addEventListener('click', function(){
      mBtn.setAttribute('aria-pressed','true'); yBtn.setAttribute('aria-pressed','false');
      document.querySelectorAll('.price-monthly').forEach(function(e){e.style.display='';});
      document.querySelectorAll('.price-yearly').forEach(function(e){e.style.display='none';});
    });
    yBtn.addEventListener('click', function(){
      yBtn.setAttribute('aria-pressed','true'); mBtn.setAttribute('aria-pressed','false');
      document.querySelectorAll('.price-monthly').forEach(function(e){e.style.display='none';});
      document.querySelectorAll('.price-yearly').forEach(function(e){e.style.display='';});
    });
  }
})();
</script>

</body>
</html>
