<?php include 'includes/header.php'; ?>

<div class="page-wrapper">
<main class="main-content" id="main">

    <!-- Breadcrumb -->
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <div class="breadcrumb-inner">
            <a href="index.php">Home</a>
            <span>&#8250;</span>
            <span aria-current="page">Best Practice</span>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero" style="margin-top:1.5rem;">
        <div class="hero-eyebrow"><?php echo icon('star', 18); ?> Best Practice</div>
        <h1>Best Practice for Housing Digital Transformation</h1>
        <p>Evidence-based best practice drawn from MSP programme management, the Scottish Approach to Service Design, GDS guidance, PROSCI change management, Rogers' diffusion of innovation, poka-yoke work practices, Audit Scotland findings, and sector experience in housing digital transformation.</p>
    </section>

    <div class="callout info" style="margin-top:1.5rem;">
        <div class="callout-icon"><?php echo icon('info', 16); ?></div>
        <div>
            <h4>How I Use This Page</h4>
            <p style="margin:0;">These best practice principles are directly embedded in the delivery plan on the <a href="presentation.php">Presentation page</a>. As I believe that being able to name the principles, cite their sources, and explain why each one matters for <em>this specific programme</em> is fundamental in being able to clearly work towards outcomes and communicate the work as a whole with ease to all stakeholders.</p>
        </div>
    </div>

    <div class="section-header" style="margin-top:2rem;">
        <h2>Best Practice by Theme</h2>
    </div>

    <!-- Programme Delivery -->
    <div class="bp-section">
        <h3><span class="bp-icon"><?php echo icon('cog', 18); ?></span> Programme Delivery</h3>
        <p style="font-size:0.88rem; color:#6B7280; margin-bottom:1rem;">Principles drawn from MSP (Managing Successful Programmes), Audit Scotland's digital transformation reviews, and GDS delivery guidance.</p>
        <ul class="bp-list">
            <li><strong>Agile within governance:</strong> Use agile sprint-based delivery for build phases, within an MSP-style programme governance framework. Agile without governance creates chaos; governance without agility creates delay. The hybrid model delivers both speed and accountability.</li>
            <li><strong>Name the Senior Responsible Owner from day one:</strong> The SRO (Head of Housing) is personally accountable for the programme achieving its benefits. This is not a symbolic role &mdash; the SRO chairs the Programme Board, escalates to the Chief Executive when needed, and remains in post for the duration.</li>
            <li><strong>Name the Benefits Owner separately from the Programme Manager:</strong> The Benefits Owner is responsible for realising the benefits &mdash; not delivering the technology. Separating these roles prevents the common failure where a programme is declared &ldquo;delivered&rdquo; because the technology was deployed, but the benefits were never measured or realised.</li>
            <li><strong>Baseline before building:</strong> Capture all benefit metrics at Month 2 before any changes are made. First-time fix rate, admin time per officer, tenant satisfaction, digital channel share &mdash; all measured before the programme touches anything. Without a baseline, it is impossible to demonstrate improvement. Audit Scotland identified this as the most common failure in council digital programmes.</li>
            <li><strong>Phase gates with real teeth:</strong> Each phase gate (Discovery Report, Contract Award, Alpha Demo, Beta Sign-off, Go-Live) requires formal sign-off from the Programme Board before proceeding. The sign-off criteria are defined at the start of the programme, not written retrospectively to match what was delivered.</li>
            <li><strong>Procurement planning starts in Phase 0:</strong> Scottish Government procurement rules mean a compliant ICT procurement process takes 12&ndash;16 weeks minimum. Start procurement planning in Phase 0 so the ITT can be published at the start of Phase 1. Procurement delay is the most common cause of slippage in council digital programmes.</li>
            <li><strong>Lessons learned throughout, not just at the end:</strong> Monthly retrospectives embedded throughout the programme. Issues are surfaced and addressed during delivery, not written up in a report after the programme has closed and the team has dispersed.</li>
            <li><strong>Exception management, not the illusion of control:</strong> Programme managers cannot control every variable. The exception protocol defines clearly what triggers escalation to the Programme Board outside the regular reporting cycle &mdash; so the board is never surprised by a major issue at a monthly meeting.</li>
        </ul>
    </div>

    <!-- SAtSD -->
    <div class="bp-section">
        <h3><span class="bp-icon"><?php echo icon('home', 18); ?></span> Scottish Approach to Service Design (SAtSD)</h3>
        <p style="font-size:0.88rem; color:#6B7280; margin-bottom:1rem;">The mandated service design methodology for Scottish public sector digital services. All tenant-facing elements of the Housing Digital Transformation must follow this approach.</p>
        <ul class="bp-list">
            <li><strong>Five stages, not a waterfall:</strong> Discover &rarr; Define &rarr; Develop &rarr; Test &rarr; Implement. The stages are iterative &mdash; findings from testing can (and should) send you back to define or develop before implementing. Building something that has not been properly tested with real users is the most common cause of post-launch rework.</li>
            <li><strong>User research with real tenants, not assumed needs:</strong> The discovery phase must include structured user research with actual ERC tenants &mdash; interviews, contextual observations, diary studies. The question is not &ldquo;what features do tenants want?&rdquo; but &ldquo;what are tenants trying to achieve and what barriers prevent them?&rdquo;</li>
            <li><strong>Recruit a diverse tenant panel:</strong> The research and testing panel must include tenants who are elderly, have disabilities, speak English as a second language, and have limited digital experience. A portal designed only by and for digitally confident tenants will fail to serve a significant proportion of ERC's housing population.</li>
            <li><strong>Accessibility is designed in, not bolted on:</strong> WCAG 2.1 AA accessibility is a design requirement from the first wireframe, not a compliance check applied to a completed build. Retrofitting accessibility is significantly more expensive and time-consuming than building it in from the start.</li>
            <li><strong>Iterative prototyping before build:</strong> Low-fidelity paper prototypes and clickable wireframes are tested with tenants before a single line of production code is written. Failures at prototype stage cost hours to fix; failures at production stage cost months.</li>
            <li><strong>Avoid &ldquo;build it and they will come&rdquo;:</strong> A well-designed portal that no one uses has not delivered channel shift. Active channel shift strategy &mdash; communications campaign, community outreach, staff guidance for tenants calling in &mdash; is as important as the technology. Dundee and Glasgow both learned this.</li>
            <li><strong>SAtSD does not end at implementation:</strong> Post-implementation user research identifies what is working and what needs iteration. The service should be treated as a continuously improving product, not a delivered project.</li>
        </ul>
    </div>

    <!-- Digital Inclusion -->
    <div class="bp-section">
        <h3><span class="bp-icon"><?php echo icon('users', 18); ?></span> Tenant Digital Inclusion</h3>
        <p style="font-size:0.88rem; color:#6B7280; margin-bottom:1rem;">Digital transformation of a public housing service has a legal and moral obligation not to disadvantage tenants who are digitally excluded. This requires active planning, not passive assurance.</p>
        <ul class="bp-list">
            <li><strong>Identify digitally excluded tenants in the discovery phase:</strong> Do not assume how many tenants are digitally excluded. Use the Phase 0 tenant research to establish the proportion of ERC's 3,141 households that do not have or cannot confidently use digital devices, and categorise by type of barrier (no device, no connectivity, no confidence, disability).</li>
            <li><strong>Parallel non-digital channels must remain available and high quality:</strong> The telephone and in-person repair reporting channels must be maintained throughout and after the digital transformation. The goal is channel shift through digital attractiveness, not channel elimination through service degradation. This is a Scottish tenant participation requirement.</li>
            <li><strong>Buddy and peer support models:</strong> Community learning partnerships, peer support networks, and library-based digital access points can support tenants who want to use the portal but need help. ERC already has community learning infrastructure that can be mobilised.</li>
            <li><strong>SMS and telephone fallback for status updates:</strong> Real-time status updates should be available via SMS for tenants who do not have or use smartphones. &ldquo;Your repair is confirmed for Tuesday between 9&ndash;12&rdquo; by text is more valuable to a digitally excluded tenant than a portal notification they cannot access.</li>
            <li><strong>Simple, accessible design from the start:</strong> Plain English, large touch targets, high contrast, logical screen flow, no jargon. The harder it is to use, the more the digitally uncertain will abandon it and return to the phone. Good inclusive design benefits all users.</li>
            <li><strong>Public Sector Equality Duty applies:</strong> Under the Equality Act 2010 and the Public Sector Equality Duty (Scotland), ERC must consider the impact of digital transformation on tenants who share protected characteristics (age, disability, race/language). A digital inclusion impact assessment should be completed in Phase 0.</li>
        </ul>
    </div>

    <!-- Data & Integration -->
    <div class="bp-section">
        <h3><span class="bp-icon"><?php echo icon('bar-chart', 18); ?></span> Data &amp; Integration</h3>
        <p style="font-size:0.88rem; color:#6B7280; margin-bottom:1rem;">Data architecture and system integration decisions made early in a programme define its long-term agility and cost. The wrong decisions create expensive, fragile dependencies.</p>
        <ul class="bp-list">
            <li><strong>Define the &ldquo;golden record&rdquo; for tenant and property before procurement:</strong> What is the authoritative source of truth for tenant identity, tenancy status, property details, and repair history? Define this architecture before selecting a housing ICT platform &mdash; not after. ERC's Golden Record PoC (Housing/Shielding/Homecare data matching) provides valuable learning here.</li>
            <li><strong>APIs and open standards over proprietary integrations:</strong> When evaluating housing ICT platforms, require RESTful API access as a minimum. Avoid platforms that require proprietary connectors for every integration &mdash; they create vendor lock-in and are expensive to maintain. Scottish Government policy supports open standards.</li>
            <li><strong>Data governance and GDPR review from Phase 0:</strong> Any system handling tenant data requires a Data Protection Impact Assessment (DPIA) before build begins. The legal basis for data processing, data retention periods, and data sharing arrangements must be agreed with the Data Protection Officer before the first line of configuration.</li>
            <li><strong>Data quality is a programme workstream, not a problem to solve later:</strong> Housing management data is often incomplete, inconsistent, and duplicated after years in legacy systems. A dedicated data cleansing and migration workstream must run in parallel with the build phase. Aberdeenshire Council's 3-month data migration overrun nearly derailed their programme.</li>
            <li><strong>Power BI dashboards for real-time programme KPIs:</strong> Build the benefits tracking dashboard in Power BI from Month 3. The Programme Board should see live KPI data at every meeting, not a slide with last quarter's numbers. Power BI is already in use at ERC and available within the M365 subscription.</li>
            <li><strong>Benefits measurement framework: baseline, track, report, adjust:</strong> Benefits realisation is not a report written at the end of the programme. It is a continuous cycle of measuring, comparing to baseline, reporting to the board, and adjusting activity to improve trajectory. Set up the measurement framework in Phase 0 and run it throughout.</li>
        </ul>
    </div>

    <!-- Rogers, Willing/Able/Allowed, Poka-yoke -->
    <div class="bp-section">
        <h3><span class="bp-icon"><?php echo icon('trending-up', 18); ?></span> Rogers' Diffusion of Innovation &amp; Adoption Theory</h3>
        <p style="font-size:0.88rem; color:#6B7280; margin-bottom:1rem;">Rogers' diffusion of innovation (1962) describes how new ideas and practices spread through populations. Combined with the &ldquo;willing, able, allowed&rdquo; adoption model, these frameworks inform how we plan for workforce and tenant adoption.</p>
        <ul class="bp-list">
            <li><strong>People adopt at different rates &mdash; plan for the adoption curve:</strong> Rogers identified five adopter categories: Innovators (2.5%), Early Adopters (13.5%), Early Majority (34%), Late Majority (34%), and Laggards (16%). Do not expect uniform adoption. Pilot with innovators and early adopters; design communications and support for each segment. The early majority will follow once they see peer success.</li>
            <li><strong>Willing, able, and allowed &mdash; the three conditions for adoption:</strong> For someone to adopt a new way of working, they must be <strong>willing</strong> (motivated, see the benefit, want to change), <strong>able</strong> (have the skills, training, support, and confidence to use the new system), and <strong>allowed</strong> (policies, procedures, and systems permit the new behaviour; managers support it; there are no structural barriers). If any one condition is missing, adoption fails. Assess each for both workforce and tenants in the discovery phase.</li>
            <li><strong>Address barriers in each dimension:</strong> Willingness requires clear benefit communication and visible success stories. Ability requires training, documentation, and peer support (Digital Champions). Allowed requires removing policy blockers (e.g. &ldquo;we've always done it this way&rdquo;), ensuring managers endorse the change, and designing systems that do not force workarounds back to the old process.</li>
        </ul>
    </div>

    <!-- Poka-yoke -->
    <div class="bp-section">
        <h3><span class="bp-icon"><?php echo icon('shield', 18); ?></span> Poka-yoke Work Practices</h3>
        <p style="font-size:0.88rem; color:#6B7280; margin-bottom:1rem;">Poka-yoke (mistake-proofing) is a lean methodology from Toyota: design processes and systems so that errors cannot occur or are immediately detected. Applied to housing digital transformation, it reduces rework, improves data quality, and makes correct practice the path of least resistance.</p>
        <ul class="bp-list">
            <li><strong>Design for correct behaviour by default:</strong> Mandatory fields, validation rules, and workflow constraints prevent incomplete or incorrect data entry. The tenant repairs portal should not allow submission without required information; the operative app should require photos or sign-off before job completion. The system guides users toward correct completion.</li>
            <li><strong>Prevent the error, not just detect it:</strong> Where possible, eliminate steps where errors can occur (e.g. auto-populate from tenant record, pre-fill appointment slots based on availability). Where elimination is not possible, make incorrect input impossible (e.g. dropdowns instead of free text for repair category, date pickers instead of manual date entry).</li>
            <li><strong>Visual and immediate feedback:</strong> Users should know immediately if something is wrong &mdash; inline validation, clear error messages, and confirmation steps before irreversible actions. Poka-yoke is not about blaming users for mistakes; it is about designing so mistakes are hard to make.</li>
        </ul>
    </div>

    <!-- Workforce Change -->
    <div class="bp-section">
        <h3><span class="bp-icon"><?php echo icon('users', 18); ?></span> Workforce Change Management</h3>
        <p style="font-size:0.88rem; color:#6B7280; margin-bottom:1rem;">Technology delivers no productivity benefit if staff do not use it, do not trust it, or actively resist it. Workforce change management is not a communications exercise &mdash; it is a structured programme discipline.</p>
        <ul class="bp-list">
            <li><strong>Staff involvement from day one &mdash; no surprises:</strong> Housing officers and repairs operatives should be involved in the discovery and requirements process, not consulted at the end. People support what they help create. The question &ldquo;what would make your job easier?&rdquo; asked genuinely in Phase 0 changes the tone of the whole programme.</li>
            <li><strong>Apply PROSCI ADKAR to workforce change:</strong> The ADKAR model (Awareness, Desire, Knowledge, Ability, Reinforcement) provides a structured framework for supporting each staff member through change. Each stage requires different interventions: awareness communications, manager conversations, training, coaching, and peer recognition.</li>
            <li><strong>Digital Champions as peer change agents:</strong> ERC's 30+ Digital Champions are the most powerful asset for workforce adoption. Assign Champions to each affected team during the programme; give them the information, training, and time to support colleagues. Peer-to-peer change is consistently more effective than top-down mandates.</li>
            <li><strong>Role-specific training using staff personas:</strong> Housing officers have different training needs from repairs operatives, who have different needs from supervisors and managers. Develop staff personas during discovery and design role-specific training journeys &mdash; not one generic training session for everyone.</li>
            <li><strong>Pilot with willing early adopters:</strong> Identify the teams or individuals who are most enthusiastic about the new system and use them as the Phase 3 pilot cohort. They will find the issues that need fixing before full rollout, and their positive experience creates social proof for their colleagues.</li>
            <li><strong>Celebrate quick wins publicly:</strong> When a team reports their first week of zero paper job cards, or an operative books their 100th digital job completion, share the story. Visible progress builds momentum and reduces resistance. Internal communications (Teams, intranet, team meetings) should carry a steady stream of &ldquo;it's working&rdquo; stories throughout the programme.</li>
            <li><strong>Reinforce the change after go-live:</strong> The ADKAR &ldquo;Reinforcement&rdquo; stage is the most commonly neglected. Six months after go-live, are staff still using the system correctly? Are managers holding team members to the new ways of working? Post-go-live reinforcement activities must be planned and resourced, not assumed to happen automatically.</li>
        </ul>
    </div>

</main>
<?php include 'includes/footer.php'; ?>
