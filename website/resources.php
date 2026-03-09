<?php require_once 'includes/header.php'; ?>

<div class="page-wrapper">
<main class="main-content" id="main">

    <!-- Breadcrumb -->
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <div class="breadcrumb-inner">
            <a href="index.php">Home</a>
            <span>&#8250;</span>
            <span aria-current="page">Research Resources</span>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero" style="margin-top:1.5rem;">
        <div class="hero-eyebrow"><?php echo icon('link', 18); ?> Research Resources</div>
        <h1>Useful Resources &amp; References</h1>
        <p>Categorised links to Scottish Government guidance, housing sector bodies, digital delivery frameworks, and tools relevant to the Housing Digital Transformation role. Each resource includes a description and a note on its relevance.</p>
    </section>

    <!-- Tabs -->
    <div class="tabs" style="margin-top:1.5rem;">
        <div class="tab-nav" role="tablist" aria-label="Resource categories">
            <button class="tab-btn active" role="tab" aria-selected="true"><?php echo icon('flag', 16); ?> Scottish Government</button>
            <button class="tab-btn" role="tab" aria-selected="false"><?php echo icon('home', 16); ?> Housing &amp; Repairs</button>
            <button class="tab-btn" role="tab" aria-selected="false"><?php echo icon('cog', 16); ?> Digital Delivery</button>
            <button class="tab-btn" role="tab" aria-selected="false"><?php echo icon('building', 16); ?> ERC Specific</button>
            <button class="tab-btn" role="tab" aria-selected="false"><?php echo icon('layers', 16); ?> Tools &amp; Frameworks</button>
            <button class="tab-btn" role="tab" aria-selected="false"><?php echo icon('play', 16); ?> Portfolio &amp; Media</button>
        </div>

        <!-- Tab 1: Scottish Government -->
        <div class="tab-panel active" role="tabpanel">
            <div class="section-header">
                <h2>Scottish Government Resources</h2>
                <p>Official Scottish Government guidance, strategy documents, and shared platforms directly relevant to the Housing Digital Transformation programme.</p>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">Scottish Approach to Service Design (SAtSD)</div>
                    <span class="badge badge-blue">Web</span>
                </div>
                <div class="resource-desc">The official Scottish Government guide to the five-stage service design methodology: Discover, Define, Develop, Test, Implement. Includes templates, guidance notes, and case studies. All tenant-facing digital services in Scotland should follow this methodology. ERC has 100+ SAtSD-trained staff.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Core methodology for the tenant portal design and all resident-facing elements of the programme. Non-negotiable in a Scottish public sector context.</div>
                <a href="https://www.gov.scot/publications/the-scottish-approach-to-service-design/" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">Digital Scotland Strategy 2021&ndash;2027</div>
                    <span class="badge badge-blue">Report/Web</span>
                </div>
                <div class="resource-desc">Scotland's national digital strategy. Sets the policy direction for public sector digital, including shared platforms, digital inclusion, and the role of local government. ERC's own Digital Transformation Strategy explicitly aligns with and references this national document.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> The national policy backdrop for everything ERC is doing digitally. Reference in interview to show you understand ERC's work in its wider policy context.</div>
                <a href="https://www.gov.scot/publications/a-changing-nation-how-scotland-will-thrive-in-a-digital-world/" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">Scottish Digital Academy</div>
                    <span class="badge badge-green">Training</span>
                </div>
                <div class="resource-desc">The Scottish Government's public sector digital skills training organisation. Offers courses in agile delivery, user research, service design, data analysis, and digital leadership. ERC has invested significantly in SDA training for staff. Relevant to workforce development planning within the programme.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Workforce capability development during the programme. Housing staff upskilling in digital skills can be delivered through SDA as part of Phase 1 activities.</div>
                <a href="https://digitalacademy.gov.scot/" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">ScotAccount / mygov.scot &mdash; Digital Identity &amp; Integration</div>
                    <span class="badge badge-teal">Technical/Web</span>
                </div>
                <div class="resource-desc">Technical documentation and guidance for integrating with the mygov.scot citizen account platform &mdash; Scotland's national digital identity and SSO infrastructure. ERC's MyEastRen portal already uses mygov.scot SSO; the Housing Repairs tenant portal will extend this.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> The tenant portal will use mygov.scot SSO &mdash; tenants already have accounts. Shows integration thinking with national platforms rather than building yet another login system.</div>
                <a href="https://scotaccount.campaign.gov.scot/documentation" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">Housing to 2040</div>
                    <span class="badge badge-blue">Report/PDF</span>
                </div>
                <div class="resource-desc">Scottish Government's 25-year housing strategy covering supply, quality, affordability, fuel poverty, and tenant rights. Digital-enabled housing management is a theme. EPC Band B by 2032 for social housing is a key target that creates both the need and the case for IoT sensor data and proactive maintenance.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Policy backdrop for the housing programme. EPC targets make IoT sensors a strategic investment, not just a nice-to-have. Tenant participation requirements underpin SAtSD co-design mandate.</div>
                <a href="https://www.gov.scot/publications/housing-2040-2/" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">Public Contracts Scotland &mdash; Procurement Portal</div>
                    <span class="badge badge-gold">Procurement/Web</span>
                </div>
                <div class="resource-desc">The national procurement portal for Scottish public sector contracts. All ICT procurement by Scottish councils must follow Scottish procurement rules. Relevant to Phase 1 of the programme (Housing ICT platform procurement). Understanding procurement timelines is critical to the programme plan.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Phase 1 procurement planning. Scottish procurement rules mean I need to allow 3&ndash;4 months minimum for a compliant ICT procurement process &mdash; this is why procurement planning starts in Phase 0 of my delivery plan.</div>
                <a href="https://www.publiccontractsscotland.gov.uk/" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>
        </div>

        <!-- Tab 2: Housing & Repairs -->
        <div class="tab-panel" role="tabpanel">
            <div class="section-header">
                <h2>Housing &amp; Repairs Resources</h2>
                <p>Sector bodies, benchmarking data, and specialist housing technology sources.</p>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">SFHA &mdash; Scottish Federation of Housing Associations</div>
                    <span class="badge badge-blue">Sector Body/Web</span>
                </div>
                <div class="resource-desc">The lead sector body for housing associations in Scotland. Publishes research on digital maturity, tenant experience, and housing management best practice. The SFHA Digital Housing Survey provides annual benchmarking data on digital adoption across Scottish social landlords. ERC can compare its performance against the sector.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Use SFHA survey data to benchmark ERC's ambitions. The sector average for digital repairs channel shift is ~22%; ERC's 30% target is above sector average, demonstrating genuine ambition.</div>
                <a href="https://www.sfha.co.uk/" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">Chartered Institute of Housing Scotland</div>
                    <span class="badge badge-blue">Sector Body/Web</span>
                </div>
                <div class="resource-desc">The professional body for housing practitioners in Scotland. Provides professional development, policy publications, and events on housing management innovation including digital transformation. Good source for housing management best practice and peer learning from other Scottish housing professionals.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Professional context for the role. CIH publications on digital housing provide sector-specific framing for the benefits you are targeting.</div>
                <a href="https://www.cih.org/scotland" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">Housemark &mdash; Housing Sector Benchmarking</div>
                    <span class="badge badge-gold">Benchmarking/Web</span>
                </div>
                <div class="resource-desc">UK's leading housing data and benchmarking organisation. Provides KPI comparators for repairs performance, tenant satisfaction, cost per repair, and digital channel shift. ERC should enrol in Housemark benchmarking to establish the benefits baseline and track programme performance against sector peers.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Critical for benefits realisation. Housemark data provides the sector comparators for your KPIs. A 30% channel shift, 10% FTF improvement, and 15-point satisfaction gain are all Housemark-comparable metrics.</div>
                <a href="https://www.housemark.co.uk/" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">Housing Technology Magazine</div>
                    <span class="badge badge-grey">Publication/Web</span>
                </div>
                <div class="resource-desc">Specialist UK publication covering digital transformation in social housing. Regular case studies on repairs technology, tenant portals, mobile workforce management, and AI applications in housing. A practical source of current sector thinking and technology vendor intelligence.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Staying current with sector technology trends. Good source for case studies on mobile workforce management (Kirona, Totalmobile, Optitime) relevant to the workforce productivity workstream.</div>
                <a href="https://www.housing-technology.com/" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">Wheatley Group &mdash; MyWheatley Tenant Portal</div>
                    <span class="badge badge-teal">Case Study/Web</span>
                </div>
                <div class="resource-desc">Wheatley Group (Glasgow) operates the MyWheatley portal, widely regarded as the industry-leading tenant digital experience in Scotland. Features 24/7 repair reporting, real-time job tracking, video triage for complex repairs, and automated satisfaction surveys. Useful as an aspirational benchmark for ERC's tenant portal.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> The &ldquo;gold standard&rdquo; Scottish example. Use Wheatley to articulate the vision for ERC's tenant portal &mdash; what excellent looks like in a Scottish housing context. Show the panel you know what good looks like.</div>
                <a href="https://www.wheatley-group.com/" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>
        </div>

        <!-- Tab 3: Digital Delivery -->
        <div class="tab-panel" role="tabpanel">
            <div class="section-header">
                <h2>Digital Delivery Frameworks &amp; Guidance</h2>
                <p>Programme management, agile delivery, change management, and service design methodologies applicable to the Housing Digital Transformation programme.</p>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">GDS Service Manual &mdash; GOV.UK</div>
                    <span class="badge badge-blue">Framework/Web</span>
                </div>
                <div class="resource-desc">The UK Government Digital Service's comprehensive guide to building government digital services. Covers agile delivery phases (discovery, alpha, beta, live), user research, accessibility, and the Service Standard. Scottish SAtSD is based on GDS principles; familiarity with the GDS manual provides deeper technical and delivery knowledge.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Deepens your understanding of agile delivery phases. The alpha/beta/live model in the GDS manual maps directly to the programme phases in your delivery plan.</div>
                <a href="https://www.gov.uk/service-manual" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">AXELOS MSP (Managing Successful Programmes)</div>
                    <span class="badge badge-gold">Framework/Web</span>
                </div>
                <div class="resource-desc">Managing Successful Programmes (MSP) is the AXELOS programme management framework widely used in UK public sector. Provides the governance architecture for complex multi-project programmes: roles (SRO, Programme Manager, Business Change Manager), processes (benefits management, risk management), and governance (programme board, exception reporting).</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> The governance model in your delivery plan is based on MSP principles: named SRO (Head of Housing), separate Benefits Owner, Programme Board with defined membership, exception escalation protocol. Reference MSP explicitly to show professional programme management credentials.</div>
                <a href="https://www.axelos.com/best-practice-solutions/msp" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">PROSCI ADKAR &mdash; Change Management Framework</div>
                    <span class="badge badge-teal">Framework/Web</span>
                </div>
                <div class="resource-desc">The PROSCI ADKAR model (Awareness, Desire, Knowledge, Ability, Reinforcement) is one of the most widely used individual change management frameworks in the world. Provides a structured approach to supporting people through organisational change &mdash; moving beyond training to address the psychological and motivational dimensions of adoption.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Reference ADKAR in the context of staff change management for the programme. The Digital Champions model maps well to the &ldquo;Desire&rdquo; and &ldquo;Reinforcement&rdquo; phases. Shows sophisticated understanding of why technology change fails (people, not systems).</div>
                <a href="https://www.prosci.com/adkar" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">WCAG 2.1 &mdash; Web Content Accessibility Guidelines</div>
                    <span class="badge badge-grey">Standard/Web</span>
                </div>
                <div class="resource-desc">The international standard for digital accessibility. WCAG 2.1 AA compliance is mandatory for Scottish public sector digital services under the Public Sector Bodies Accessibility Regulations 2018. The tenant portal must meet AA standard to be legally compliant and to serve all tenants including those with visual, motor, and cognitive disabilities.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Legal requirement and moral imperative. Housing tenants include elderly, disabled, and vulnerable individuals &mdash; WCAG 2.1 AA is non-negotiable. Include it in your discovery phase as a baseline requirement, not an afterthought.</div>
                <a href="https://www.w3.org/WAI/WCAG21/quickref/" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">Audit Scotland &mdash; Digital Transformation Reviews</div>
                    <span class="badge badge-red">Audit/PDF</span>
                </div>
                <div class="resource-desc">Audit Scotland's published reviews of digital transformation in Scottish public bodies. Identifies common failure patterns: lack of benefits baseline, unclear ownership, change management neglected, procurement delays. An essential read for understanding what governance must prevent in the Housing Digital programme.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Most powerful external validation for your governance approach. Every Audit Scotland failure mode is explicitly addressed in your delivery plan. Show the panel you have learned from public sector failures, not just successes.</div>
                <a href="https://audit.scot/publications/digital-progress-in-local-government" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>
        </div>

        <!-- Tab 4: ERC Specific -->
        <div class="tab-panel" role="tabpanel">
            <div class="section-header">
                <h2>ERC-Specific Resources</h2>
                <p>Documents, platforms and tools specific to East Renfrewshire Council's digital environment.</p>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">East Renfrewshire Council &mdash; Digital Transformation</div>
                    <span class="badge badge-blue">Web</span>
                </div>
                <div class="resource-desc">ERC's corporate digital transformation web pages, including published information about the MAP programme, Digital Champions network, and digital strategy progress. A starting point for researching ERC's published commitments and achievements.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Know what ERC says publicly about its digital programme. Demonstrates that you have done more than the minimum in your research.</div>
                <a href="https://www.eastrenfrewshire.gov.uk/digital-transformation" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">MyEastRen &mdash; Citizen Portal</div>
                    <span class="badge badge-teal">Web/Portal</span>
                </div>
                <div class="resource-desc">ERC's citizen self-service portal using mygov.scot SSO. The tenant housing repairs portal will be an extension of this infrastructure. Understanding the existing citizen portal &mdash; its design, user experience, and service scope &mdash; is important for the Housing Digital Transformation programme.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Experience the existing tenant-facing digital infrastructure yourself. Note the UX, the services available, and the integration points. The Housing Repairs portal needs to feel consistent with MyEastRen.</div>
                <a href="https://www.eastrenfrewshire.gov.uk/myeastren" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">ERC Housing Service &mdash; Tenant Information</div>
                    <span class="badge badge-blue">Web</span>
                </div>
                <div class="resource-desc">ERC's housing service web pages for tenants, including information on reporting repairs, housing policies, and tenant rights. Understanding the current state of tenant-facing housing information &mdash; what is already online, what is still telephone-only &mdash; informs the discovery phase of the programme.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Current state research. Map what information and transactions are already digital vs. what requires phone/in-person contact. This is your channel audit starting point for Phase 0 discovery.</div>
                <a href="https://www.eastrenfrewshire.gov.uk/housing" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">ERC Housing Asset Management Plan 2024&ndash;2026</div>
                    <span class="badge badge-gold">Report/PDF</span>
                </div>
                <div class="resource-desc">The HAMP document is available on ERC's website. It contains the formal scoping of the Digital Repairs project, the housing stock data (3,141 units), energy efficiency targets, and the investment priorities that give the Housing Digital Transformation programme its strategic business case.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Essential reading. The single most important ERC-specific document for the interview. Contains the numbers (3,141 units, 171 new builds, EPC Band B by 2032) and the mandate for the Digital Repairs programme.</div>
                <a href="https://www.eastrenfrewshire.gov.uk/housing-asset-management-plan" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>
        </div>

        <!-- Tab 5: Tools & Frameworks -->
        <div class="tab-panel" role="tabpanel">
            <div class="section-header">
                <h2>Tools &amp; Delivery Frameworks</h2>
                <p>Practical tools, technology platforms, and delivery frameworks relevant to programme planning and execution.</p>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">Microsoft Power BI &mdash; Analytics &amp; Dashboards</div>
                    <span class="badge badge-blue">Tool/Web</span>
                </div>
                <div class="resource-desc">ERC already has Power BI as part of its M365 subscription. The benefits realisation dashboard for the Housing Digital programme will be built in Power BI, providing real-time visibility of KPIs (channel shift, FTF rate, satisfaction scores, admin time savings) to the Programme Board and senior leadership.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> No additional cost. Already available and in use across ERC. The programme benefits dashboard should be built in Power BI as a standard deliverable from Month 3 onwards.</div>
                <a href="https://powerbi.microsoft.com/" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">Kirona / Totalmobile &mdash; Mobile Workforce Management</div>
                    <span class="badge badge-teal">Technology/Web</span>
                </div>
                <div class="resource-desc">Leading mobile workforce management platforms used extensively in UK social housing for repairs scheduling, field staff mobile apps, real-time job tracking, and route optimisation. Totalmobile and Kirona are the most widely deployed solutions in Scottish councils and housing associations for repairs workforce management.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Know the market options for the workforce productivity workstream. North Lanarkshire Council uses Optitime scheduling; many Scottish HAs use Totalmobile or Kirona. Understanding the vendor landscape demonstrates procurement readiness.</div>
                <a href="https://www.totalmobile.co.uk/" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">NEC Housing &mdash; Social Housing Management Platform</div>
                    <span class="badge badge-gold">Technology/Web</span>
                </div>
                <div class="resource-desc">NEC Housing is one of the leading housing management systems in the UK public sector, used by numerous Scottish councils including Aberdeenshire. Provides comprehensive housing management functionality including repairs, tenancy management, asset management, and tenant portal integration.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> A likely candidate in the housing ICT platform procurement (Phase 1). Aberdeenshire Council moved to NEC Housing in an 18-month agile programme &mdash; useful comparator for ERC's procurement planning.</div>
                <a href="https://www.nec-sv.com/en-gb/housing" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">Goss Interactive &mdash; CXM Platform</div>
                    <span class="badge badge-blue">Technology/Web</span>
                </div>
                <div class="resource-desc">ERC's existing Customer Experience Management platform. Goss provides the digital forms, service request workflows, and citizen account integration for ERC's MyEastRen portal. The Housing Repairs digital channel will integrate with Goss CXM to provide a unified view of tenant interactions and to avoid creating a disconnected silo.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Already deployed and in use at ERC. The integration architecture for Phase 2 must consider Goss CXM as the service request hub. Understanding Goss's API capabilities informs the integration design.</div>
                <a href="https://www.gossinteractive.com/" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title">CivTech Scotland &mdash; Innovation Accelerator</div>
                    <span class="badge badge-green">Innovation/Web</span>
                </div>
                <div class="resource-desc">Scotland's government technology accelerator. Connects public sector challenges with technology companies through a structured challenge-response programme. Graduates can be procured through Public Contracts Scotland using a simplified process. Relevant for innovative housing technology (AI triage, video triage, predictive maintenance) that sits outside standard procurement frameworks.</div>
                <div class="resource-relevance"><?php echo icon('info', 14); ?> Innovation procurement route. Post-programme, CivTech offers a route to innovative technologies (AI repair diagnosis, video triage) without lengthy traditional tender. Shows strategic thinking about future service evolution.</div>
                <a href="https://www.civtech.scot/" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;">Visit Resource &#8599;</a>
            </div>
        </div>

        <!-- Tab 6: Portfolio & Media -->
        <div class="tab-panel" role="tabpanel">
            <div class="section-header">
                <h2>Portfolio &amp; Media</h2>
                <p>Interview preparation materials: video overview, infographics, presentation slides, and CV. Available to view online or download.</p>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title"><?php echo icon('play', 18); ?> Housing Digital Transformation &mdash; Video Overview</div>
                    <span class="badge badge-teal">Video</span>
                </div>
                <div class="resource-desc">A video I created as a visual summary of the Housing Digital Transformation approach, key themes, and delivery vision. Suitable for sharing with interviewers as evidence of preparation depth.</div>
                <div class="media-video-wrapper" style="margin:1rem 0; border-radius:var(--radius-md); overflow:hidden; background:#000;">
                    <video controls style="width:100%; display:block; vertical-align:top;" poster="">
                        <source src="assets/media/videoplayback.mp4" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title"><?php echo icon('photo', 18); ?> Infographics</div>
                    <span class="badge badge-blue">Image</span>
                </div>
                <div class="resource-desc">Visual summaries of the Housing Digital Transformation programme structure, themes, and delivery plan.</div>
                <div class="media-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:1rem; margin-top:1rem;">
                    <a href="assets/media/InfoGraphic.png" target="_blank" rel="noopener" class="media-thumb" style="border:1px solid var(--erc-border); border-radius:var(--radius-sm); overflow:hidden; display:block;">
                        <img src="assets/media/InfoGraphic.png" alt="Housing Digital Transformation infographic" loading="lazy" style="width:100%; height:140px; object-fit:cover;">
                        <span style="display:block; padding:0.5rem; font-size:0.8rem; background:var(--erc-surface-alt); text-align:center;">InfoGraphic</span>
                    </a>
                    <a href="assets/media/New%20infographis.png" target="_blank" rel="noopener" class="media-thumb" style="border:1px solid var(--erc-border); border-radius:var(--radius-sm); overflow:hidden; display:block;">
                        <img src="assets/media/New%20infographis.png" alt="Housing Digital Transformation infographic" loading="lazy" style="width:100%; height:140px; object-fit:cover;">
                        <span style="display:block; padding:0.5rem; font-size:0.8rem; background:var(--erc-surface-alt); text-align:center;">Digital transformation infographic</span>
                    </a>
                </div>
                <p style="margin-top:0.75rem; font-size:0.85rem; color:var(--erc-text-light);"><?php echo icon('info', 14); ?> Click to open full size or right-click to download.</p>
            </div>

            <div class="resource-card">
                <div class="resource-card-top">
                    <div class="resource-card-title"><?php echo icon('document', 18); ?> ERC Digital Strategy (PowerPoint)</div>
                    <span class="badge badge-gold">Download</span>
                </div>
                <div class="resource-desc">East Renfrewshire Council's published Digital Transformation Strategy in PowerPoint format. Source document for understanding ERC's digital direction and strategic context.</div>
                <a href="assets/media/ERC_Digital_Strategy.pptx" download class="btn btn-outline" style="margin-top:0.5rem; font-size:0.8rem; padding:0.35rem 0.75rem;"><?php echo icon('printer', 14); ?> Download PowerPoint (.pptx)</a>
            </div>

        </div>
    </div><!-- /.tabs -->

</main>
<?php require_once 'includes/footer.php'; ?>
