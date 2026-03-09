-- ERC Interview Research Portal — Database Setup
-- Run this script to initialise the database and seed data

CREATE DATABASE IF NOT EXISTS erc_interview CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE erc_interview;

-- Resources table
CREATE TABLE IF NOT EXISTS resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL,
    description TEXT,
    category VARCHAR(100) NOT NULL,
    added_date DATE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notes table
CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_ref VARCHAR(100) NOT NULL,
    note_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bookmarks table
CREATE TABLE IF NOT EXISTS bookmarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL,
    tags VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: 10 relevant resources
INSERT INTO resources (title, url, description, category, added_date) VALUES
('Scottish Approach to Service Design (SAtSD)', 'https://www.gov.scot/publications/the-scottish-approach-to-service-design/', 'Official Scottish Government guidance on the five-stage service design methodology: Discover, Define, Develop, Test, Implement. Mandatory reference for all public-facing digital services in Scotland.', 'Scottish Government', '2024-01-15'),
('Digital Scotland Strategy 2021–2027', 'https://www.gov.scot/publications/scotlands-digital-strategy/', 'National strategy setting Scotland''s ambition to be a world-leading digital nation, covering public services, connectivity, economy and skills. Underpins ERC''s own digital transformation agenda.', 'Scottish Government', '2024-01-15'),
('Housing to 2040 — Scottish Government', 'https://www.gov.scot/publications/housing-2040-2/', 'Scotland''s 25-year housing strategy covering quality, affordability, supply and digital-enabled housing management. Key policy context for the ERC Housing Digital Transformation role.', 'Scottish Government', '2024-01-20'),
('mygov.scot — Developer and Service Integration Documentation', 'https://www.mygov.scot/developer/', 'Technical documentation for integrating with the national mygov.scot citizen account (Scottish Government SSO). Relevant to ERC''s MyEastRen portal and planned Housing Repairs tenant portal.', 'Scottish Government', '2024-01-20'),
('Scottish Digital Academy — Training Catalogue', 'https://www.scottishdigitalacademy.gov.scot/', 'Training programmes in service design, agile delivery, user research and data for Scottish public sector staff. ERC has sent 100+ staff through SDA courses; relevant to workforce development planning.', 'Scottish Government', '2024-02-01'),
('SFHA Digital Housing Survey 2023', 'https://www.sfha.co.uk/sfha/research-and-policy/research/', 'Scottish Federation of Housing Associations annual digital maturity benchmarking survey. Provides sector baseline data on tenant portal adoption, mobile workforce, and digital channel shift across Scottish RSLs.', 'Housing & Repairs', '2024-02-05'),
('GDS Service Standard — GOV.UK', 'https://www.gov.uk/service-manual/service-standard', 'The UK Government Digital Service''s 14-point service standard. Scottish equivalent is SAtSD. Both frameworks require user research, iterative delivery, accessibility and measurable outcomes.', 'Digital Delivery', '2024-02-10'),
('Audit Scotland: Digital Transformation in Scottish Councils', 'https://www.audit-scotland.gov.uk/uploads/docs/report/2021/nr_210527_digital_transformation.pdf', 'Audit Scotland''s 2021 review of digital transformation in Scottish councils, identifying key success factors and common failure modes. Directly applicable to programme governance and benefits realisation planning.', 'Digital Delivery', '2024-02-10'),
('COSLA / Scottish Government Local Government Digital Programme', 'https://www.gov.scot/policies/digital/local-government-digital/', 'Joint COSLA and Scottish Government programme to support councils in digital transformation. Includes shared platforms, funding streams and peer learning. ERC participates in this programme.', 'Scottish Government', '2024-02-15'),
('Housemark — Housing Sector Benchmarking', 'https://www.housemark.co.uk/', 'UK''s leading housing data and benchmarking organisation. Provides KPI comparators for repairs performance, tenant satisfaction, cost per repair and digital channel shift. Essential for setting ERC''s benefits baseline and targets.', 'Housing & Repairs', '2024-02-20');
