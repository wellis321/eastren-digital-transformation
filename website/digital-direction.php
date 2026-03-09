<?php include 'includes/header.php'; ?>

<div class="page-wrapper page-digital-direction">
<main class="main-content" id="main">

    <!-- Breadcrumb -->
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <div class="breadcrumb-inner">
            <a href="index.php">Home</a>
            <span>&#8250;</span>
            <span aria-current="page">Future Digital Direction</span>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero" style="margin-top:1.5rem;">
        <div class="hero-eyebrow">Future Digital Direction</div>
        <h1>Where Housing Digital Is Heading</h1>
        <p>Beyond the 20-month programme: the emerging technologies and digital approaches that will shape the future of social housing management. Understanding these trends demonstrates strategic vision and the ability to think beyond the immediate delivery horizon.</p>
        <div class="hero-meta">
            <div class="hero-meta-item"><?php echo icon('clock', 16); ?> <strong>Horizon:</strong>&nbsp;2025&ndash;2030</div>
            <div class="hero-meta-item"><?php echo icon('building', 16); ?> <strong>Focus:</strong>&nbsp;AI, IoT, Automation, Citizen Experience</div>
            <div class="hero-meta-item"><?php echo icon('info', 16); ?> <strong>Note:</strong>&nbsp;Post-programme strategic context</div>
        </div>
    </section>

    <div class="callout info" style="margin-top:1.5rem;">
        <div class="callout-icon"><?php echo icon('info', 16); ?></div>
        <div>
            <h4>Why I Think About Future Direction Now</h4>
            <p style="margin:0;">I think about the future of housing digital not as a separate topic from the 20-month programme, but as the context that shapes every decision I make within it. The technology choices, the platforms adopted, the data architecture put in place — all of these should be designed with the next horizon in mind. ERC has already laid strong foundations: the IoT pilot across 50 properties, M365 embedding for 2,000+ staff, the Digital Champions network. My role is to extend those foundations purposefully, not just solve today's problem.</p>
        </div>
    </div>

    <!-- AI & Automation -->
    <div class="section-header" style="margin-top:2rem;">
        <h2>AI &amp; Automation in Housing Repairs</h2>
    </div>

    <div class="three-col">
        <div class="direction-card">
            <span class="direction-card-icon"><?php echo icon('target', 18); ?></span>
            <h3>Predictive Maintenance</h3>
            <p>Machine learning models trained on IoT sensor data (temperature, humidity, CO2) and historical repairs data to predict failures before they occur.</p>
            <ul>
                <li>Boiler failure prediction from performance telemetry</li>
                <li>Damp/mould risk from humidity trend data</li>
                <li>Structural alerts from vibration sensors</li>
                <li>Seasonal demand forecasting for workforce planning</li>
            </ul>
            <p style="margin-top:0.75rem;"><strong>ERC foundation:</strong> 50-property IoT sensor pilot already in operation. Expansion to 3,141 units makes predictive maintenance viable at scale.</p>
            <div style="margin-top:0.75rem;"><span class="badge badge-gold">3&ndash;5 year horizon</span></div>
        </div>

        <div class="direction-card">
            <span class="direction-card-icon"><?php echo icon('activity', 18); ?></span>
            <h3>AI Repair Triage</h3>
            <p>Tenants describe a repair in natural language; AI categorises the repair type, assigns the correct trade, estimates required parts, and routes to the right operative.</p>
            <ul>
                <li>Natural language processing of repair descriptions</li>
                <li>Automatic trade assignment (joiner, plumber, electrician)</li>
                <li>Parts prediction to reduce unnecessary revisits</li>
                <li>Priority scoring: urgent vs. routine, vulnerability flags</li>
            </ul>
            <p style="margin-top:0.75rem;"><strong>Already in production:</strong> Sanctuary Housing UK has deployed AI triage, achieving 25% reduction in unnecessary contractor visits.</p>
            <div style="margin-top:0.75rem;"><span class="badge badge-gold">2&ndash;4 year horizon</span></div>
        </div>

        <div class="direction-card">
            <span class="direction-card-icon"><?php echo icon('info', 18); ?></span>
            <h3>Chatbot Repair Reporting</h3>
            <p>24/7 automated repair logging through a conversational AI interface, handling routine repairs without human advisor involvement.</p>
            <ul>
                <li>Guided troubleshooting before logging (can tenant resolve it themselves?)</li>
                <li>Automatic appointment booking and confirmation</li>
                <li>Escalation to human advisor for complex or sensitive cases</li>
                <li>Integration with mygov.scot account for personalised experience</li>
            </ul>
            <p style="margin-top:0.75rem;"><strong>Edinburgh pilot:</strong> Edinburgh City Council ran a chatbot pilot for housing repairs &mdash; technically successful but adoption needed active promotion.</p>
            <div style="margin-top:0.75rem;"><span class="badge badge-gold">2&ndash;3 year horizon</span></div>
        </div>

        <div class="direction-card">
            <span class="direction-card-icon"><?php echo icon('trending-up', 18); ?></span>
            <h3>Demand Forecasting</h3>
            <p>AI models using historical repairs data, seasonal patterns, property age/condition, and IoT sensor trends to forecast repairs demand volumes and types.</p>
            <ul>
                <li>Workforce planning: how many operatives of which trades next month?</li>
                <li>Van stock management: which parts need replenishing?</li>
                <li>Budget forecasting: projected cost of reactive maintenance</li>
                <li>Planned maintenance scheduling: optimal timing to minimise disruption</li>
            </ul>
            <p style="margin-top:0.75rem;"><strong>Data requirement:</strong> Requires 2&ndash;3 years of clean, structured repairs history in the new housing system before models are viable. Start capturing data quality from programme day one.</p>
            <div style="margin-top:0.75rem;"><span class="badge badge-teal">4&ndash;6 year horizon</span></div>
        </div>

        <div class="direction-card">
            <span class="direction-card-icon"><?php echo icon('calendar', 18); ?></span>
            <h3>Automated Scheduling Optimisation</h3>
            <p>AI-powered workforce scheduling that goes beyond basic route optimisation to dynamically replan the day based on real-time factors.</p>
            <ul>
                <li>Real-time job completion updates feeding into next job dispatching</li>
                <li>Emergency prioritisation: automatic rescheduling when an urgent job is logged</li>
                <li>Skills matching: ensure the operative has the right trade and equipment</li>
                <li>Traffic and travel time integration for realistic scheduling</li>
            </ul>
            <p style="margin-top:0.75rem;"><strong>Near-term:</strong> North Lanarkshire's Optitime scheduling delivers significant scheduling optimisation today. AI-enhanced versions are the next step.</p>
            <div style="margin-top:0.75rem;"><span class="badge badge-blue">1&ndash;2 year horizon</span></div>
        </div>

        <div class="direction-card">
            <span class="direction-card-icon"><?php echo icon('cog', 18); ?></span>
            <h3>RPA &amp; Process Automation</h3>
            <p>Robotic Process Automation to eliminate manual, repetitive tasks in the repairs administration process.</p>
            <ul>
                <li>Automated job creation from portal repair reports</li>
                <li>Automated compliance certificate generation and filing</li>
                <li>Automated satisfaction survey dispatch on job completion</li>
                <li>Automated performance reporting to Scottish Housing Regulator</li>
            </ul>
            <p style="margin-top:0.75rem;"><strong>ERC foundation:</strong> Microsoft Power Automate (part of M365) enables low-code RPA without additional licensing. Phase 4 deliverable in the programme plan.</p>
            <div style="margin-top:0.75rem;"><span class="badge badge-blue">0&ndash;1 year horizon (in programme)</span></div>
        </div>
    </div>

    <!-- IoT -->
    <hr class="section-divider">
    <div class="section-header">
        <h2>Internet of Things in Social Housing</h2>
    </div>

    <div class="callout success">
        <div class="callout-icon"><?php echo icon('check-circle', 16); ?></div>
        <div>
            <h4>ERC's IoT Head Start</h4>
            <p style="margin:0;">ERC already has an IoT sensor pilot operating in 50 housing properties. This is not theoretical &mdash; the technology, data infrastructure, supplier relationships, and management processes have already been tested. Scaling to 3,141 units is a strategic business case decision, not a technical leap into the unknown.</p>
        </div>
    </div>

    <div class="two-col" style="margin-top:1.5rem;">
        <div>
            <div class="direction-card">
                <span class="direction-card-icon"><?php echo icon('cpu', 18); ?></span>
                <h3>Smart Property Sensors at Scale</h3>
                <p>Expanding ERC's existing 50-property IoT pilot to the full 3,141-unit housing stock. Each property fitted with temperature, humidity, and CO2 sensors providing continuous environmental monitoring.</p>
                <ul>
                    <li>Early damp and mould identification before visible damage</li>
                    <li>Heating system performance monitoring</li>
                    <li>Fuel poverty early identification (properties consistently cold)</li>
                    <li>Evidence base for EESSH2 Band B compliance planning</li>
                </ul>
                <p style="margin-top:0.75rem; font-size:0.82rem; color:#6B7280;">Business case: cost of reactive damp/mould repair vs cost of sensor and early intervention. Clarion Housing has demonstrated positive ROI at scale.</p>
            </div>
        </div>
        <div>
            <div class="direction-card">
                <span class="direction-card-icon"><?php echo icon('activity', 18); ?></span>
                <h3>Asset Performance Monitoring</h3>
                <p>Digital monitoring of key housing assets to predict servicing needs and failure before they occur.</p>
                <ul>
                    <li>Boiler telemetry: performance data and pre-failure alerts from connected boilers</li>
                    <li>Lift monitoring: vibration and load data for predictive servicing</li>
                    <li>Water leak detection: moisture sensors in kitchens and bathrooms</li>
                    <li>Fire and smoke detection integration: real-time status monitoring for compliance</li>
                </ul>
                <p style="margin-top:0.75rem; font-size:0.82rem; color:#6B7280;">Reduces emergency callout volume and extends asset lifecycles through optimised planned maintenance scheduling.</p>
            </div>
        </div>
        <div>
            <div class="direction-card">
                <span class="direction-card-icon"><?php echo icon('bolt', 18); ?></span>
                <h3>Smart Meter Integration</h3>
                <p>Linking ERC's housing management data with smart meter data (where available and consented) to identify fuel poverty risk at property level.</p>
                <ul>
                    <li>Identify properties consuming disproportionately high energy (potential insulation/heating issue)</li>
                    <li>Proactive contact for fuel poverty support referral</li>
                    <li>Evidence base for insulation improvement prioritisation</li>
                    <li>Integration with national fuel poverty support schemes</li>
                </ul>
                <p style="margin-top:0.75rem; font-size:0.82rem; color:#6B7280;">Requires consent framework and GDPR-compliant data sharing arrangement with energy suppliers or via national smart meter data infrastructure.</p>
            </div>
        </div>
        <div>
            <div class="direction-card">
                <span class="direction-card-icon"><?php echo icon('layers', 18); ?></span>
                <h3>Digital Twin Potential</h3>
                <p>A digital twin of ERC's housing stock: a continuously updated virtual model of each property's condition, systems, and maintenance history.</p>
                <ul>
                    <li>Virtual model integrates IoT sensor data, inspection records, repairs history, and energy data</li>
                    <li>Enables scenario modelling: what if we insulated these 500 properties?</li>
                    <li>Supports HAMP investment planning with live condition data</li>
                    <li>Integration with BIM (Building Information Modelling) for new build and major works</li>
                </ul>
                <p style="margin-top:0.75rem; font-size:0.82rem; color:#6B7280;">5&ndash;10 year vision. Requires significant data infrastructure investment and clean, structured data from the housing management system.</p>
            </div>
        </div>
    </div>

    <!-- Workforce Technology -->
    <hr class="section-divider">
    <div class="section-header">
        <h2>Future Workforce Technology</h2>
    </div>

    <div class="three-col">
        <div class="direction-card">
            <span class="direction-card-icon"><?php echo icon('star', 18); ?></span>
            <h3>Augmented Reality (AR) for Complex Repairs</h3>
            <p>Remote expert guidance for complex repairs: a senior technician can see what a field operative sees through smart glasses or a phone camera, and guide them through the repair step by step.</p>
            <ul>
                <li>Reduce need to escalate complex jobs to specialist contractors</li>
                <li>Knowledge transfer from retiring experienced staff to newer operatives</li>
                <li>Training and upskilling on-the-job, in real scenarios</li>
            </ul>
            <div style="margin-top:0.75rem;"><span class="badge badge-teal">Emerging technology</span></div>
        </div>

        <div class="direction-card">
            <span class="direction-card-icon"><?php echo icon('shield', 18); ?></span>
            <h3>Wearables &amp; Lone Worker Safety</h3>
            <p>Extending ERC's existing wearable technology pilot (HAVS monitoring in Roads Service) to Housing repairs operatives.</p>
            <ul>
                <li>Lone worker safety: GPS tracking, panic alarm, automated welfare check</li>
                <li>PPE compliance monitoring: are operatives wearing required protective equipment?</li>
                <li>Physical strain monitoring: repetitive movement tracking for H&amp;S compliance</li>
                <li>Fatigue alerts for operatives working extended shifts</li>
            </ul>
            <div style="margin-top:0.75rem;"><span class="badge badge-blue">Near-term (2&ndash;3 years)</span></div>
        </div>

        <div class="direction-card">
            <span class="direction-card-icon"><?php echo icon('map-pin', 18); ?></span>
            <h3>Automated Van Stocking</h3>
            <p>Data-driven parts inventory management for repairs vans, using historical repairs data and demand forecasting to ensure operatives carry the right parts for the day's jobs.</p>
            <ul>
                <li>Reduce &ldquo;no part&rdquo; aborted visits: a key driver of poor first-time fix rate</li>
                <li>Just-in-time parts reordering from supplier</li>
                <li>Van stock optimisation by operative specialism and area</li>
                <li>Waste reduction: fewer returns of unused parts</li>
            </ul>
            <div style="margin-top:0.75rem;"><span class="badge badge-gold">2&ndash;4 year horizon</span></div>
        </div>
    </div>

    <!-- Citizen Experience -->
    <hr class="section-divider">
    <div class="section-header">
        <h2>Future Citizen Experience</h2>
    </div>

    <div class="three-col">
        <div class="direction-card">
            <span class="direction-card-icon"><?php echo icon('presentation', 18); ?></span>
            <h3>Video Triage</h3>
            <p>Tenants film the problem using their smartphone before a contractor is dispatched. A housing officer or AI system reviews the video to assess the repair, assign the correct trade, and predict required parts.</p>
            <ul>
                <li>Wheatley Group: 20% reduction in unnecessary contractor visits</li>
                <li>Better first-time fix rate: right trade, right parts, first time</li>
                <li>Immediate feasibility check: is this actually ERC's responsibility?</li>
            </ul>
            <div style="margin-top:0.75rem;"><span class="badge badge-gold">2&ndash;3 year horizon</span></div>
        </div>

        <div class="direction-card">
            <span class="direction-card-icon"><?php echo icon('map-pin', 18); ?></span>
            <h3>Real-Time Job Tracking</h3>
            <p>&ldquo;Uber for repairs&rdquo;: tenants can see where their operative is in real time on the day of the repair, with a live ETA update.</p>
            <ul>
                <li>Eliminates uncertainty: &ldquo;they said between 8 and 12, it's now 11&rdquo;</li>
                <li>Reduces &ldquo;I missed the appointment&rdquo; aborted visits</li>
                <li>Increases tenant satisfaction significantly</li>
                <li>Already available in Wheatley's MyWheatley portal</li>
            </ul>
            <div style="margin-top:0.75rem;"><span class="badge badge-blue">1&ndash;2 year horizon</span></div>
        </div>

        <div class="direction-card">
            <span class="direction-card-icon"><?php echo icon('memo', 18); ?></span>
            <h3>Proactive Communications</h3>
            <p>Automated, personalised communications to tenants at every stage of the repair lifecycle, via their preferred channel.</p>
            <ul>
                <li>Confirmation immediately on repair report</li>
                <li>Appointment reminder (48 hours, 24 hours, 2 hours)</li>
                <li>Operative ETA on day of repair</li>
                <li>Job completion notification</li>
                <li>Automated satisfaction survey within 24 hours of completion</li>
            </ul>
            <div style="margin-top:0.75rem;"><span class="badge badge-blue">In programme (Phase 2&ndash;4)</span></div>
        </div>

        <div class="direction-card">
            <span class="direction-card-icon"><?php echo icon('globe', 18); ?></span>
            <h3>mygov.scot Integration: Single View</h3>
            <p>As mygov.scot matures, the vision is for a single digital account giving Scottish citizens a unified view of all their interactions with Scottish public bodies.</p>
            <ul>
                <li>Repairs history alongside council tax, benefits, and planning applications</li>
                <li>Proactive alerts about relevant services (e.g. winter fuel support)</li>
                <li>Cross-service personalisation: ERC knows the tenant's circumstances holistically</li>
            </ul>
            <div style="margin-top:0.75rem;"><span class="badge badge-teal">3&ndash;5 year national platform vision</span></div>
        </div>

        <div class="direction-card">
            <span class="direction-card-icon"><?php echo icon('bar-chart', 18); ?></span>
            <h3>Tenant Satisfaction Intelligence</h3>
            <p>Moving beyond the post-repair survey to continuous sentiment analysis and multi-channel satisfaction monitoring.</p>
            <ul>
                <li>Automated post-repair survey with NPS scoring</li>
                <li>Analysis of free-text complaint and compliment patterns (NLP)</li>
                <li>Social media monitoring for housing service sentiment</li>
                <li>Real-time satisfaction dashboard for service managers</li>
            </ul>
            <div style="margin-top:0.75rem;"><span class="badge badge-gold">2&ndash;4 year horizon</span></div>
        </div>

        <div class="direction-card">
            <span class="direction-card-icon"><?php echo icon('users', 18); ?></span>
            <h3>Tenant Empowerment Tools</h3>
            <p>Digital tools that give tenants more control and visibility over their tenancy, not just repairs reporting.</p>
            <ul>
                <li>Digital rent account and payment history</li>
                <li>Tenancy document access (tenancy agreement, gas safety certificate)</li>
                <li>Community noticeboard and tenant panel digital engagement</li>
                <li>Personal energy performance data with improvement recommendations</li>
            </ul>
            <div style="margin-top:0.75rem;"><span class="badge badge-teal">3&ndash;5 year horizon</span></div>
        </div>
    </div>

    <!-- Data & Analytics -->
    <hr class="section-divider">
    <div class="section-header">
        <h2>Data &amp; Analytics Evolution</h2>
    </div>

    <div class="two-col">
        <div class="policy-block">
            <h3><?php echo icon('bar-chart', 16); ?> Real-Time Performance Intelligence</h3>
            <p>Beyond the programme KPI dashboard: a live, always-on analytics environment for the housing service that enables data-driven management decisions at every level.</p>
            <ul>
                <li><strong>Power BI Live dashboards:</strong> Real-time repairs performance &mdash; jobs logged, in progress, completed today; SLA compliance by trade and area</li>
                <li><strong>Predictive SLA alerts:</strong> Flag jobs approaching SLA breach before they breach &mdash; proactive management, not reactive apologising</li>
                <li><strong>Cost per repair analysis:</strong> Trend analysis by repair type, trade, operative, and area to identify efficiency opportunities</li>
                <li><strong>Housemark benchmarking integration:</strong> Automatic comparison of ERC performance against sector peers, updated quarterly</li>
            </ul>
        </div>

        <div class="policy-block">
            <h3><?php echo icon('target', 16); ?> Predictive Asset Investment Modelling</h3>
            <p>Using the housing management data, IoT sensor data, and inspection records to model the future investment requirements of ERC's housing stock.</p>
            <ul>
                <li>Which properties will need roofing replacement in the next 5 years, based on age and condition data?</li>
                <li>What is the projected cost of achieving EPC Band B across the stock?</li>
                <li>Which properties have the highest reactive maintenance cost per year &mdash; and would investment in planned improvements reduce this?</li>
                <li>HAMP investment planning supported by live, accurate asset condition data rather than periodic manual surveys</li>
            </ul>
            <p style="margin-top:0.75rem; font-size:0.82rem; color:#6B7280;">Requires clean, structured property and repairs data from the new housing management system. This is one of the long-term benefits of the Housing Digital Transformation programme &mdash; building the data asset that enables intelligent investment planning.</p>
        </div>
    </div>

    <!-- Summary -->
    <div class="callout highlight">
        <div class="callout-icon"><?php echo icon('bolt', 18); ?></div>
        <div>
            <h4>The Strategic Vision: Housing That Knows Its Own Condition</h4>
            <p>The long-term vision for ERC's housing digital transformation is a housing service that is <strong>proactive rather than reactive</strong>: sensors that identify problems before tenants report them; data systems that predict maintenance needs before assets fail; scheduling systems that optimise workforce productivity automatically; and citizen-facing tools that give tenants genuine transparency and control. The 20-month programme is the foundation &mdash; building the data infrastructure, digital channels, and workforce capability on which these future capabilities depend. Getting the foundation right is the most important thing.</p>
        </div>
    </div>

</main>
<?php include 'includes/footer.php'; ?>
