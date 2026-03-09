<?php include 'includes/header.php'; ?>

<div class="page-wrapper">
<main class="main-content" id="main">

    <!-- Breadcrumb -->
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <div class="breadcrumb-inner">
            <a href="index.php">Home</a>
            <span>&#8250;</span>
            <span aria-current="page">Sector Comparisons</span>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero" style="margin-top:1.5rem;">
        <div class="hero-eyebrow"><?php echo icon('globe', 18); ?> Sector Comparisons</div>
        <h1>What Others Are Doing in Housing Digital</h1>
        <p>Comparisons with Scottish councils and housing associations that have undertaken repairs and housing digital transformation. Key lessons learned, outcomes achieved, and models ERC can learn from or adapt.</p>
        <div class="hero-meta">
            <div class="hero-meta-item"><?php echo icon('building', 16); ?> <strong>Councils covered:</strong>&nbsp;6 Scottish councils</div>
            <div class="hero-meta-item"><?php echo icon('home', 16); ?> <strong>HAs covered:</strong>&nbsp;3 housing associations</div>
            <div class="hero-meta-item"><?php echo icon('chart-bar', 16); ?> <strong>Focus:</strong>&nbsp;Repairs digital transformation</div>
        </div>
    </section>

    <div class="callout info" style="margin-top:1.5rem;">
        <div class="callout-icon"><?php echo icon('info', 16); ?></div>
        <div>
            <h4>Why Sector Comparison Matters</h4>
            <p style="margin:0;">Knowing what other organisations have done &mdash; and what has worked and what has not &mdash; demonstrates that you are not proposing a generic plan, but a contextually informed one. Each comparison below includes a specific lesson that can be applied to the ERC Housing Digital Transformation programme.</p>
        </div>
    </div>

    <!-- Scottish Councils -->
    <div class="section-header" style="margin-top:2rem;">
        <h2>Scottish Councils</h2>
        <p>Digital repairs and housing transformation programmes in comparable Scottish local authorities.</p>
    </div>

    <div class="tabs sector-tabs">
        <div class="tab-nav" role="tablist" aria-label="Scottish councils">
            <button class="tab-btn active" role="tab" aria-selected="true"><?php echo icon('building', 14); ?> Glasgow</button>
            <button class="tab-btn" role="tab" aria-selected="false"><?php echo icon('building', 14); ?> Edinburgh</button>
            <button class="tab-btn" role="tab" aria-selected="false"><?php echo icon('building', 14); ?> North Lanarkshire</button>
            <button class="tab-btn" role="tab" aria-selected="false"><?php echo icon('building', 14); ?> Dundee</button>
            <button class="tab-btn" role="tab" aria-selected="false"><?php echo icon('building', 14); ?> Aberdeenshire</button>
            <button class="tab-btn" role="tab" aria-selected="false"><?php echo icon('building', 14); ?> South Lanarkshire</button>
        </div>

        <div class="tab-panel active" role="tabpanel">
        <div class="sector-card sector-card-tabbed">
            <div class="sector-card-org"><?php echo icon('building', 14); ?> Glasgow City Council</div>
            <div class="sector-card-title">MyGlasgow App &mdash; 40% Digital Channel Shift</div>
            <div class="sector-card-body">
                <p>Glasgow City Council developed the MyGlasgow mobile app and web portal for repairs reporting, integrating directly with their housing management system. The platform allows tenants to report repairs, select appointment slots, and receive status updates by push notification or SMS.</p>
                <p>Glasgow achieved approximately <strong>40% digital channel shift</strong> for repairs reporting within 18 months of full launch, making them one of the highest-performing councils in Scotland for this metric. The app has over 80,000 registered users across all council services.</p>
                <strong>Outcomes:</strong>
                <ul style="margin-top:0.3rem;">
                    <li>40% repairs reported digitally (vs sector avg 22%)</li>
                    <li>Significant reduction in call centre volume for repairs</li>
                    <li>Tenant satisfaction with repairs improved 12 points post-launch</li>
                </ul>
            </div>
            <div class="sector-lesson">
                <span><?php echo icon('info', 14); ?></span>
                <div><strong>Lesson for ERC:</strong> A strong, proactive communications campaign is essential for channel shift. Glasgow's success was as much about marketing the app to tenants as it was about building it. Digital is available but tenants need to be actively encouraged, not just informed, to use it.</div>
            </div>
        </div>
        </div>

        <div class="tab-panel" role="tabpanel">
        <div class="sector-card sector-card-tabbed">
            <div class="sector-card-org"><?php echo icon('building', 14); ?> Edinburgh City Council</div>
            <div class="sector-card-title">Housing Repairs Chatbot Pilot &amp; Salesforce Integration</div>
            <div class="sector-card-body">
                <p>Edinburgh City Council ran a pilot of an AI-powered chatbot for housing repairs triage, integrated with their Salesforce CRM platform. The chatbot was designed to handle initial repairs categorisation and appointment booking, routing complex cases to human advisors.</p>
                <p>The pilot demonstrated technical feasibility but revealed significant challenges: <strong>tenant adoption was much slower than expected</strong>, particularly among older tenants who were unfamiliar with chatbot interaction patterns. The chatbot required significant fine-tuning to handle the natural language variation in how tenants describe repair problems.</p>
                <strong>Outcomes:</strong>
                <ul style="margin-top:0.3rem;">
                    <li>Chatbot handled ~35% of queries without human intervention in month 6</li>
                    <li>Tenant adoption significantly below projections in initial months</li>
                    <li>Required substantial comms and outreach to drive usage</li>
                </ul>
            </div>
            <div class="sector-lesson">
                <span><?php echo icon('info', 14); ?></span>
                <div><strong>Lesson for ERC:</strong> Tenant adoption does not happen automatically. Edinburgh found that without proactive outreach &mdash; including community-level engagement, not just online promotion &mdash; many tenants defaulted to phone even when the digital option was available. Build the comms campaign into the programme plan from Phase 0.</div>
            </div>
        </div>
        </div>

        <div class="tab-panel" role="tabpanel">
        <div class="sector-card sector-card-tabbed">
            <div class="sector-card-org"><?php echo icon('building', 14); ?> North Lanarkshire Council</div>
            <div class="sector-card-title">Optitime Mobile Workforce Management</div>
            <div class="sector-card-body">
                <p>North Lanarkshire Council implemented Optitime as their repairs workforce scheduling and mobile management solution. The system provides AI-assisted scheduling optimisation, real-time GPS tracking of repairs vans and operatives, automated job dispatching, and mobile job completion recording.</p>
                <p>The deployment focused on workforce productivity as the primary benefit, with tenant experience improvements as a secondary outcome. Field-based repairs operatives were equipped with tablets providing real-time job details, property repair history, compliance records, and the ability to complete and sign-off jobs electronically.</p>
                <strong>Outcomes:</strong>
                <ul style="margin-top:0.3rem;">
                    <li><strong>15% improvement in first-time fix rate</strong> within 12 months</li>
                    <li>Significant reduction in end-of-day admin paperwork</li>
                    <li>Improved van stock management through data-driven parts prediction</li>
                </ul>
            </div>
            <div class="sector-lesson">
                <span><?php echo icon('info', 14); ?></span>
                <div><strong>Lesson for ERC:</strong> The first-time fix rate improvement came primarily from operatives having access to full property repair history on their device &mdash; they knew what they were going to before they arrived, and could bring the right parts. Data quality in the housing system is a prerequisite for mobile workforce benefit.</div>
            </div>
        </div>
        </div>

        <div class="tab-panel" role="tabpanel">
        <div class="sector-card sector-card-tabbed">
            <div class="sector-card-org"><?php echo icon('building', 14); ?> Dundee City Council</div>
            <div class="sector-card-title">SAtSD Co-Design Approach &mdash; Tenant Panel Model</div>
            <div class="sector-card-body">
                <p>Dundee City Council undertook their housing digital repairs programme using the Scottish Approach to Service Design in full, including establishment of a dedicated tenant panel that was involved throughout the design and testing process. Dundee is widely regarded in the Scottish sector as the best practice example of how SAtSD should be applied to housing services.</p>
                <p>The tenant panel included representation from older tenants, tenants with disabilities, non-English-speaking tenants, and digitally inexperienced tenants &mdash; ensuring the design was accessible and usable by the full range of residents, not just the digitally confident.</p>
                <strong>Outcomes:</strong>
                <ul style="margin-top:0.3rem;">
                    <li>Portal achieved WCAG 2.1 AA from launch (no remediation required)</li>
                    <li>Tenant satisfaction with portal significantly higher than sector benchmarks</li>
                    <li>Cited by Scottish Government as best practice case study</li>
                </ul>
            </div>
            <div class="sector-lesson">
                <span><?php echo icon('info', 14); ?></span>
                <div><strong>Lesson for ERC:</strong> Investing in proper co-design with a diverse tenant panel pays back significantly in higher adoption rates, better accessibility, and avoidance of costly remediation post-launch. ERC should recruit a tenant panel in Phase 0 and keep them involved throughout the programme.</div>
            </div>
        </div>
        </div>

        <div class="tab-panel" role="tabpanel">
        <div class="sector-card sector-card-tabbed">
            <div class="sector-card-org"><?php echo icon('building', 14); ?> Aberdeenshire Council</div>
            <div class="sector-card-title">Phased NEC Housing System Replacement</div>
            <div class="sector-card-body">
                <p>Aberdeenshire Council undertook a complex legacy housing management system replacement, moving to NEC Housing over an 18-month programme using an agile delivery model. The programme covered data migration from the legacy system, staff training, integration with mobile workforce tools, and a phased rollout by area.</p>
                <p>The programme was notable for its use of agile sprints within a formal programme governance structure &mdash; exactly the hybrid approach recommended for ERC. Procurement was identified as the most significant risk, and they allowed 4 months for the procurement process alone, having learned from delays in a previous ICT project.</p>
                <strong>Outcomes:</strong>
                <ul style="margin-top:0.3rem;">
                    <li>Delivered broadly on time within the 18-month window</li>
                    <li>Data migration was the most challenging element &mdash; took 3 months longer than planned</li>
                    <li>Staff adoption significantly better than previous system change, attributed to earlier involvement</li>
                </ul>
            </div>
            <div class="sector-lesson">
                <span><?php echo icon('info', 14); ?></span>
                <div><strong>Lesson for ERC:</strong> Data migration consistently takes longer than planned. Build in a dedicated data migration workstream from Phase 1 and do not underestimate the effort required to cleanse, map, and validate data from legacy housing systems. Aberdeenshire's 3-month overrun on data migration nearly derailed the whole programme.</div>
            </div>
        </div>
        </div>

        <div class="tab-panel" role="tabpanel">
        <div class="sector-card sector-card-tabbed">
            <div class="sector-card-org"><?php echo icon('building', 14); ?> South Lanarkshire Council</div>
            <div class="sector-card-title">Digital Telecare Transition &amp; Vulnerable Resident Inclusion</div>
            <div class="sector-card-body">
                <p>South Lanarkshire Council was one of the first Scottish councils to complete the Digital Telecare Transition programme (analogue to digital telecare devices) ahead of the 2025 analogue switch-off deadline. The programme included a dedicated digital inclusion workstream for vulnerable residents who were at risk of being left behind.</p>
                <p>The digital inclusion model used community connectors and third-sector partners to provide one-to-one support for elderly and disabled residents. Lessons learned were formally published and shared with other councils through the COSLA Local Government Digital Programme peer network.</p>
                <strong>Outcomes:</strong>
                <ul style="margin-top:0.3rem;">
                    <li>Transition completed ahead of national deadline</li>
                    <li>Zero service disruption for vulnerable residents during transition</li>
                    <li>Digital inclusion model cited as best practice by Scottish Government</li>
                </ul>
            </div>
            <div class="sector-lesson">
                <span><?php echo icon('info', 14); ?></span>
                <div><strong>Lesson for ERC:</strong> Vulnerable resident digital inclusion cannot be an add-on. South Lanarkshire's success came from treating it as a dedicated, resourced workstream with third-sector involvement from the start. ERC's housing tenants include elderly, disabled, and digitally excluded residents who need specific support, not just a &ldquo;help page&rdquo;.</div>
            </div>
        </div>
        </div>

    </div><!-- /.tabs.sector-tabs -->

    <!-- Housing Associations -->
    <hr class="section-divider">
    <div class="section-header">
        <h2>Housing Associations</h2>
        <p>UK housing associations that have led innovation in digital repairs and tenant experience &mdash; including the Scottish sector-leading Wheatley Group.</p>
    </div>

    <div class="tabs sector-tabs">
        <div class="tab-nav" role="tablist" aria-label="Housing associations">
            <button class="tab-btn active" role="tab" aria-selected="true"><?php echo icon('home', 14); ?> Wheatley</button>
            <button class="tab-btn" role="tab" aria-selected="false"><?php echo icon('home', 14); ?> Sanctuary</button>
            <button class="tab-btn" role="tab" aria-selected="false"><?php echo icon('home', 14); ?> Clarion</button>
        </div>

        <div class="tab-panel active" role="tabpanel">
        <div class="sector-card sector-card-tabbed" style="border-top-color: var(--erc-gold);">
            <div class="sector-card-org"><?php echo icon('home', 14); ?> Wheatley Group (Glasgow)</div>
            <div class="sector-card-title">MyWheatley Portal &mdash; Scottish Sector Leader</div>
            <div class="sector-card-body">
                <p>Wheatley Group, Scotland's largest social landlord (80,000+ homes), has developed the MyWheatley portal into what is widely regarded as the industry-leading tenant digital experience in Scotland. The platform offers 24/7 repair reporting with a simple, mobile-first interface; real-time job tracking (described as &ldquo;Uber for repairs&rdquo;); video triage for complex repairs where tenants film the problem before a contractor is dispatched; and automated satisfaction surveys immediately post-repair completion.</p>
                <p>The portal is fully integrated with Wheatley's housing management system, providing operatives with full property history before arrival. Scheduling is AI-assisted, grouping jobs by trade, location, and urgency.</p>
                <strong>Outcomes:</strong>
                <ul style="margin-top:0.3rem;">
                    <li>Over 60% of repairs reported digitally</li>
                    <li>Video triage reduced unnecessary contractor visits by 20%</li>
                    <li>Tenant satisfaction with repairs consistently 90%+</li>
                    <li>Industry awards for tenant digital experience</li>
                </ul>
            </div>
            <div class="sector-lesson">
                <span><?php echo icon('star', 14); ?></span>
                <div><strong>Lesson for ERC / Aspiration to reference:</strong> Wheatley is the &ldquo;gold standard&rdquo; for Scottish social housing digital. Use it to articulate the vision for where ERC's programme is heading: 24/7 self-service, real-time tracking, video triage, and proactive satisfaction measurement &mdash; not just a form to fill in.</div>
            </div>
        </div>
        </div>

        <div class="tab-panel" role="tabpanel">
        <div class="sector-card sector-card-tabbed">
            <div class="sector-card-org"><?php echo icon('home', 14); ?> Sanctuary Housing (UK-wide)</div>
            <div class="sector-card-title">AI-Powered Repair Diagnosis Tool</div>
            <div class="sector-card-body">
                <p>Sanctuary Housing (115,000+ homes across England) deployed an AI-powered repair diagnosis tool that processes natural language repair descriptions from tenants and automatically categorises the repair type, assigns the correct trade, and estimates parts required. The system was trained on years of historical repair data and continuously improves.</p>
                <p>The AI triage tool sits between the tenant-facing portal and the back-end job management system, providing an intelligent routing layer that reduces the need for skilled housing officers to categorise every repair manually.</p>
                <strong>Outcomes:</strong>
                <ul style="margin-top:0.3rem;">
                    <li>25% reduction in unnecessary contractor visits</li>
                    <li>Improved first-time fix rate through correct trade assignment</li>
                    <li>Significant reduction in repairs officer time spent on categorisation</li>
                </ul>
            </div>
            <div class="sector-lesson">
                <span><?php echo icon('info', 14); ?></span>
                <div><strong>Lesson for ERC:</strong> AI repair triage is not science fiction &mdash; it is already in production in UK housing. This is a realistic Phase 4/5 or post-programme enhancement for ERC, building on the base portal and data infrastructure. Reference this as the &ldquo;next horizon&rdquo; in the digital direction conversation.</div>
            </div>
        </div>
        </div>

        <div class="tab-panel" role="tabpanel">
        <div class="sector-card sector-card-tabbed">
            <div class="sector-card-org"><?php echo icon('home', 14); ?> Clarion Housing (England)</div>
            <div class="sector-card-title">Predictive Maintenance Using IoT at Scale</div>
            <div class="sector-card-body">
                <p>Clarion Housing Group (125,000 homes) has deployed IoT sensors across a significant proportion of their housing stock for proactive damp, mould, and maintenance monitoring. Sensors transmit temperature, humidity, and CO2 data in real-time. Machine learning models identify properties approaching risk thresholds and trigger proactive maintenance interventions before tenants report problems.</p>
                <p>The programme dramatically reduced the volume of emergency and reactive damp/mould repairs, which are among the most costly and damaging for tenants. Clarion has published its data model and approach as an open-source resource for other housing providers.</p>
                <strong>Outcomes:</strong>
                <ul style="margin-top:0.3rem;">
                    <li>Significant reduction in emergency damp/mould repairs</li>
                    <li>Tenant health outcomes improved in sensor-fitted properties</li>
                    <li>Cost per repair (damp/mould) reduced substantially</li>
                </ul>
            </div>
            <div class="sector-lesson">
                <span><?php echo icon('info', 14); ?></span>
                <div><strong>Lesson for ERC:</strong> ERC's existing 50-property IoT sensor pilot is the start of this journey. The Clarion model shows what expanding to full stock coverage looks like in practice. Reference this when discussing ERC's IoT ambitions and the link to EPC targets and fuel poverty obligations.</div>
            </div>
        </div>
        </div>

    </div>

    <!-- Key Sector Trends -->
    <hr class="section-divider">
    <div class="section-header">
        <h2>Key Sector Trends</h2>
        <p>Technology and practice trends reshaping digital repairs and housing management across the UK sector.</p>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Trend</th>
                    <th>Description</th>
                    <th>ERC Relevance</th>
                    <th>Stage</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Mobile-First Workforce</strong></td>
                    <td>Field operatives equipped with tablets/smartphones giving real-time job details, property history, compliance records, and electronic sign-off. Platforms: Kirona, Totalmobile, Optitime.</td>
                    <td>Core deliverable of the Housing Digital Transformation programme &mdash; Phase 2&ndash;4</td>
                    <td><span class="badge badge-blue">Now</span></td>
                </tr>
                <tr>
                    <td><strong>Tenant Self-Service Portal</strong></td>
                    <td>24/7 online repairs reporting with appointment selection, real-time tracking, and automated updates. Mobile-first design. Integration with housing system and workforce scheduling.</td>
                    <td>Primary tenant-facing deliverable of the programme. Target: 30%+ channel shift by Month 18.</td>
                    <td><span class="badge badge-blue">Now</span></td>
                </tr>
                <tr>
                    <td><strong>Video Triage</strong></td>
                    <td>Tenants film the problem before a contractor is dispatched. Enables remote assessment of repair complexity, trade assignment, and parts prediction without a preliminary visit.</td>
                    <td>Post-programme enhancement (Year 2). Wheatley already doing this; 20% reduction in unnecessary visits.</td>
                    <td><span class="badge badge-gold">Emerging</span></td>
                </tr>
                <tr>
                    <td><strong>AI Repair Triage</strong></td>
                    <td>Natural language processing to categorise repair descriptions, assign correct trade, and estimate required parts. Reduces skilled officer time on categorisation. Sanctuary Housing in production.</td>
                    <td>Phase 5 or post-programme. Requires a populated data history from the repairs system to train on.</td>
                    <td><span class="badge badge-gold">Emerging</span></td>
                </tr>
                <tr>
                    <td><strong>IoT / Predictive Maintenance</strong></td>
                    <td>Sensors (humidity, temperature, CO2) triggering proactive intervention before failure. Already piloted by ERC in 50 properties. Clarion scaling to full stock.</td>
                    <td>ERC already has the proof of concept. Scaling to 3,141 units is a strategic post-programme investment case.</td>
                    <td><span class="badge badge-teal">In Use (Pilot)</span></td>
                </tr>
                <tr>
                    <td><strong>mygov.scot Integration (Scotland-specific)</strong></td>
                    <td>Tenant portals in Scotland linking to the national mygov.scot citizen account for single sign-on. Eliminates password management; aligns with national platform strategy.</td>
                    <td>Standard for ERC. MyEastRen already uses mygov.scot SSO &mdash; the repairs portal extends this.</td>
                    <td><span class="badge badge-blue">Now</span></td>
                </tr>
            </tbody>
        </table>
    </div>

</main>
<?php include 'includes/footer.php'; ?>
