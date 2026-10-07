<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'>
<head>
  <meta charset='utf-8'>
  <title class='hightlight-blue'>Enidu Batuwanthudawe</title>
  <meta name='description' content='Topic 14 – Using IT Systems in Organisations'>
  <meta name='author' content='Enidu Batuwanthudawe'>
  <meta name='apple-mobile-web-app-capable' content='yes'>
  <meta name='apple-mobile-web-app-status-bar-style' content='black-translucent'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no'>
  <link rel='stylesheet' href='<?= $pptBase ?>/css/reveal.min.css'>
  <link rel='stylesheet' href='<?= $pptBase ?>/css/theme/default.css' id='theme'>
  <link rel='stylesheet' href='<?= $pptBase ?>/css/custom.css'>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel='stylesheet' href='<?= $pptBase ?>/lib/css/zenburn.css'>
  <?php if (isset($_GET['print-pdf'])): ?>
  <link rel="stylesheet" href="<?= $pptBase ?>/css/print/pdf.css">
  <?php endif; ?>
  <!--[if lt IE 9]>
  <script src='<?= $pptBase ?>/lib/js/html5shiv.js'></script>
  <![endif]-->
  <script src='<?= $pptBase ?>/js/moment.min.js'></script>
</head>
<body>
  <div class='reveal'>
    <div class='slides'>
      <section>
        <h4>Topic 14</h4>
        <h2>Using IT Systems in Organisations</h2>
        <?= ppt_teacher_credit_markup() ?>
      </section>
      <section>
        <section>
          <h4><b>1) </b>Define an IT system.</h4>
        </section>
        <section>
          <p>An IT system (Information Technology system) is a combination of hardware, software,<br>data, people, and procedures that work together to collect, process, store, and output<br>information.<br>An IT system takes input (data), processes it using software and hardware, and produces<br>useful output (information) to support users or organizations.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>2) </b>State two reasons why organisations rely on IT systems.</h4>
        </section>
        <section>
          <p>Two reasons are improved efficiency and better decision-making. IT systems automate routine work and process data quickly, while reports and up-to-date information help managers make informed decisions.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>3) </b>Explain how IT systems improve productivity.</h4>
        </section>
        <section>
          <p>1. Automation of Repetitive Tasks<br>IT systems automate routine activities such as payroll processing, stock updates, invoicing,<br>and data entry.<br>This reduces the time employees spend on repetitive work, allowing them to focus on more<br>important tasks.<br>2. Faster Data Processing<br>Computers can process large amounts of data quickly compared to manual methods.<br>For example, financial calculations, report generation, and sales analysis can be completed<br>in seconds rather than hours.<br>3. Improved Communication<br>Email, messaging platforms, and video conferencing allow instant communication between<br>employees, departments, and customers.<br>This reduces delays and speeds up decision-making.<br>4. Better Access to Information<br>Databases and cloud systems allow employees to quickly retrieve accurate information<br>when needed.<br>Less time is wasted searching for paper files or outdated records.<br>5. Reduced Errors<br>Automated systems reduce human mistakes in calculations and data handling.<br>Fewer errors mean less time spent correcting problems, which increases overall efficiency.<br>6. Remote Working Capabilities<br>Cloud computing and online collaboration tools allow staff to work from different locations.<br>Work continues without being limited by physical office space.<br>7. Integration of Systems<br>Modern IT systems link departments together (e.g., sales, inventory, accounting).<br>When one system updates, others update automatically, reducing duplication of work.<br>IT systems improve productivity by automating tasks, speeding up processes, improving<br>communication, reducing errors, and providing quick access to accurate information,<br>enabling organizations to achieve more output with fewer resources.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>4) </b>Explain how IT systems support decision-making.</h4>
        </section>
        <section>
          <p>1. Providing Accurate Information<br>IT systems collect and store data in databases.<br>Managers can access up-to-date and accurate information, reducing decisions based on<br>guesswork.<br>Example: Sales data stored in a database helps managers see which products are<br>performing well.<br>2. Generating Reports<br>Management Information Systems (MIS) automatically produce reports, summaries, charts,<br>and graphs.<br>These reports make complex data easier to understand.<br>Example: A monthly profit report helps management decide whether costs need to be<br>reduced.<br>3. Data Analysis Tools<br>IT systems use software such as spreadsheets and data analytics tools to analyses trends<br>and patterns.<br>This helps organizations predict future outcomes.<br>Example: Analyzing past sales trends can help forecast future demand.<br>4. Real-Time Information<br>Modern systems provide real-time updates.<br>Managers can respond quickly to problems or opportunities.<br>Example: Real-time stock systems alert managers when inventory is low.<br>5. Simulation and Modelling<br>Some IT systems allow “what-if” analysis to test different scenarios.<br>This helps managers evaluate risks before making decisions.<br>Example: A company can simulate the impact of increasing prices before actually changing<br>them.<br>6. Improved Communication of Decisions<br>IT systems allow decisions to be shared quickly across departments via email, dashboards,<br>or internal networks.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>5) </b>Describe how IT systems improve communication and collaboration.</h4>
        </section>
        <section>
          <p>1. Faster Communication<br>Email, instant messaging, and video conferencing allow messages to be sent and received<br>instantly.<br>This reduces delays compared to traditional mail or face-to-face meetings.<br>Effect: Faster responses improve workflow and productivity.<br>2. Real-Time Collaboration<br>Cloud-based tools allow multiple users to work on the same document at the same time.<br>Changes are updated automatically for all users.<br>Example: Team members editing a shared online document simultaneously.<br>Effect: Reduces duplication of work and improves teamwork.<br>3. File Sharing and Central Storage<br>Shared drives and cloud storage systems allow employees to access the same files from<br>different locations.<br>This ensures everyone works with the most up-to-date information.<br>Effect: Prevents version conflicts and data loss.<br>4. Remote Working Support<br>Video conferencing and collaboration platforms enable employees to work from home or<br>different branches.<br>Meetings can take place without physical travel.<br>Effect: Saves time and reduces costs.<br>5. Project Management Tools<br>IT systems provide task management software that assigns responsibilities and tracks<br>progress.<br>Team members can see deadlines and updates clearly.<br>Effect: Improves coordination and accountability.<br>6. Improved Internal and External Communication<br>Organizations can communicate easily with customers, suppliers, and partners through<br>email, websites, and customer portals.<br>Effect: Strengthens relationships and improves customer service.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>6) </b>Explain how IT systems help reduce costs and increase profit.</h4>
        </section>
        <section>
          <p>1. Automation Reduces Labor Costs<br>IT systems automate repetitive tasks such as payroll, billing, stock control, and data entry.<br>This reduces the need for large numbers of employees and lowers salary expenses.<br>Example: Automatic invoicing software reduces the need for manual accounting staff.<br>2. Reduced Paper and Administrative Costs<br>Digital records replace paper files, printing, and postage.<br>Communication through email reduces mailing expenses.<br>Effect: Lower spending on paper, ink, storage space, and postage.<br>3. Improved Efficiency and Productivity<br>Faster data processing means more work can be completed in less time.<br>Higher productivity leads to increased output without increasing costs.<br>Effect: More goods/services produced with the same resources increases profit.<br>4. Better Stock Management<br>Inventory systems monitor stock levels in real time.<br>This prevents overstocking (which ties up money) and understocking (which loses sales).<br>Effect: Reduces waste and maximizes sales revenue.<br>5. Improved Decision-Making<br>Accurate reports and data analysis help managers make better financial decisions.<br>Poor decisions can be avoided, reducing financial losses.<br>6. Online Sales and Marketing<br>E-commerce systems allow organizations to sell products globally without needing physical<br>stores.<br>Online advertising is often cheaper than traditional advertising.<br>Effect: Wider market reach increases sales revenue and profit.<br>7. Reduced Travel and Communication Costs<br>Video conferencing and online meetings reduce the need for business travel.<br>Effect: Savings on transport, accommodation, and fuel.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>7) </b>Describe how IT systems improve customer service.</h4>
        </section>
        <section>
          <p>1. Faster Response Times<br>Customer Relationship Management (CRM) systems store customer details and past<br>interactions.<br>Staff can quickly access information and respond without asking customers to repeat<br>details.<br>Effect: Customers receive quicker and more efficient service.<br>2. 24/7 Online Services<br>Websites, mobile apps, and chatbots allow customers to access services at any time.<br>Customers can place orders, check balances, or track deliveries outside normal business<br>hours.<br>Effect: Increased convenience and customer satisfaction.<br>3. Personalized Service<br>IT systems analyse customer data such as purchase history and preferences.<br>Organizations can offer personalized recommendations and targeted promotions.<br>Effect: Customers feel valued, increasing loyalty.<br>4. Accurate Order Processing<br>Automated systems reduce human error in billing, stock management, and order<br>processing.<br>Effect: Fewer mistakes mean fewer complaints and returns.<br>5. Improved Communication Channels<br>Email, live chat, social media, and messaging platforms allow customers to contact<br>businesses easily.<br>Effect: Multiple communication options improve accessibility.<br>6. Faster Problem Resolution<br>Helpdesk systems track complaints and service requests.<br>Issues can be assigned to the correct department and monitored until resolved.<br>Effect: Problems are handled more efficiently and professionally.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>8) </b>Explain the importance of security and compliance.</h4>
        </section>
        <section>
          <p>1. Protecting Sensitive Information<br>IT systems store confidential data such as customer details, financial records, and employee<br>information.<br>Security measures (e.g., passwords, encryption, firewalls) prevent unauthorized access.<br>Importance: Prevents data breaches, identity theft, and financial loss.<br>2. Maintaining Customer Trust<br>Customers expect organizations to keep their personal data safe.<br>If a security breach occurs, customers may lose confidence in the organization.<br>Importance: Protecting data helps maintain reputation and customer loyalty.<br>3. Legal Compliance<br>Organizations must follow data protection and privacy laws (e.g., data protection<br>regulations).<br>Compliance ensures data is collected, stored, and processed legally.<br>Importance: Avoids heavy fines, legal action, and business closure.<br>4. Preventing Financial Loss<br>Cyberattacks, fraud, and ransomware can cause serious financial damage.<br>Strong security systems reduce the risk of costly incidents.<br>Importance: Protects company assets and revenue.<br>5. Ensuring Business Continuity<br>Security measures such as backups and disaster recovery plans ensure that data can be<br>restored if systems fail or are attacked.<br>Importance: Reduces downtime and keeps operations running.<br>6. Protecting Intellectual Property<br>Organizations store valuable information such as designs, research data, and trade secrets.<br>Security controls prevent competitors or hackers from stealing this information.<br>Importance: Maintains competitive advantage.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>9) </b>Define automation.</h4>
        </section>
        <section>
          <p>Automation is the use of IT systems to perform tasks automatically with minimal human<br>intervention.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>10) </b>Give two examples of automated tasks.</h4>
        </section>
        <section>
          <p>Payroll processing and automatic stock reordering.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>11) </b>Explain one benefit of automation to organisations.</h4>
        </section>
        <section>
          <p>Automation reduces the time employees spend on repetitive work and can improve consistency. This allows staff to concentrate on higher-value tasks, increasing output and potentially reducing operating costs.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>12) </b>Explain one benefit of automation to customers.</h4>
        </section>
        <section>
          <p>Customers can receive faster and more consistent service because routine requests can be processed automatically. For example, an automated order or payment system can respond immediately without waiting for manual processing.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>13) </b>Explain how IT systems monitor performance.</h4>
        </section>
        <section>
          <p>They collect real-time data and generate alerts or reports when targets are not met.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>14) </b>Describe two problems monitoring can prevent.</h4>
        </section>
        <section>
          <p>Monitoring can detect system faults early, helping to prevent prolonged system downtime. It can also identify abnormal storage, network or application behaviour so that data loss or service disruption can be reduced.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>15) </b>Describe how chatbots are used in customer service.</h4>
        </section>
        <section>
          <p>Chatbots use programmed rules or AI-based language processing to answer common questions, provide information such as order status and direct more complex enquiries to human staff. They can operate continuously, including outside normal working hours.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>16) </b>Explain one advantage of online self-service portals.</h4>
        </section>
        <section>
          <p>Customers can access information and complete routine tasks such as checking balances, tracking orders or submitting requests without contacting staff. This improves convenience and reduces the workload on customer-service employees.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>17) </b>Define data analysis.</h4>
        </section>
        <section>
          <p>Data analysis is examining data to identify patterns and trends.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>18) </b>Explain how data analysis improves efficiency.</h4>
        </section>
        <section>
          <p>By analysing data, an organisation can identify bottlenecks, unnecessary steps, waste and under-used resources. Managers can then change processes or allocate resources more effectively, increasing output and reducing time and cost.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>19) </b>Define Enterprise Resource Planning (ERP).</h4>
        </section>
        <section>
          <p>ERP is an integrated system managing core business processes.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>20) </b>State three functions managed by ERP systems.</h4>
        </section>
        <section>
          <p>ERP systems commonly integrate finance/accounting, human-resource management and inventory or supply-chain management. Other modules may include sales, purchasing and production.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>21) </b>Explain one advantage of ERP systems.</h4>
        </section>
        <section>
          <p>An ERP system provides a shared source of data for different departments. This reduces duplicate data entry and inconsistent records, and allows departments to coordinate activities using current information.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>22) </b>Explain one disadvantage of ERP systems.</h4>
        </section>
        <section>
          <p>ERP systems can be expensive and complex to implement because data may need to be migrated, existing processes changed and employees trained. Implementation can also disrupt normal operations if it is poorly managed.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>23) </b>Give two digital communication tools.</h4>
        </section>
        <section>
          <p>Email and instant messaging.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>24) </b>Explain how instant messaging supports remote work.</h4>
        </section>
        <section>
          <p>Instant messaging provides rapid communication between employees in different locations. Staff can exchange updates, ask questions and coordinate tasks without waiting for meetings or email replies, helping remote teams work together.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>25) </b>Describe the role of video conferencing.</h4>
        </section>
        <section>
          <p>Video conferencing provides live audio and video communication between people in different locations. It supports meetings, presentations and discussions without travel and can include features such as screen sharing and collaborative working.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>26) </b>Give two collaboration platforms.</h4>
        </section>
        <section>
          <p>Microsoft 365 and Google Workspace.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>27) </b>Explain how cloud collaboration improves teamwork.</h4>
        </section>
        <section>
          <p>Cloud collaboration allows authorised users in different locations to access the same files and applications. Users can work on shared documents and see current changes, reducing duplicate versions and making remote teamwork easier.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>28) </b>Define project management software.</h4>
        </section>
        <section>
          <p>Software used to plan, schedule and monitor projects.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>29) </b>Describe two features of project management software.</h4>
        </section>
        <section>
          <p>Task scheduling allows tasks, deadlines and dependencies to be recorded, while progress tracking records completed work and the current status of activities. These features help managers control the project schedule.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>30) </b>Explain how it helps managers track progress.</h4>
        </section>
        <section>
          <p>Managers can view task status, deadlines, dependencies and assigned resources. Reports, dashboards or Gantt charts can highlight delays, allowing managers to reallocate resources or take corrective action before delays affect the whole project.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>31) </b>Define knowledge management.</h4>
        </section>
        <section>
          <p>The process of capturing and sharing organisational knowledge.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>32) </b>Distinguish between data, information and knowledge.</h4>
        </section>
        <section>
          <p>Data is raw facts or values. Information is data that has been processed or organised so that it has meaning. Knowledge is the understanding gained from information and experience that can be applied to decisions or actions.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>33) </b>Explain the purpose of a DMS.</h4>
        </section>
        <section>
          <p>A DMS stores, organises, retrieves and controls access to electronic documents. Features such as searching, version control and permissions help users find the correct document and prevent unauthorised changes.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>34) </b>Explain the purpose of a CMS.</h4>
        </section>
        <section>
          <p>A CMS allows authorised users to create, edit, organise and publish digital content without having to directly modify the underlying software. It can also provide permissions and workflows for reviewing and publishing content.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>35) </b>Explain how knowledge repositories support organisations.</h4>
        </section>
        <section>
          <p>Knowledge repositories provide a central place to store procedures, best practices, lessons learned and other organisational knowledge. Staff can search and reuse this information, reducing repeated work and helping preserve knowledge when experienced employees leave.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>36) </b>Define product development.</h4>
        </section>
        <section>
          <p>The process of designing and improving products.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>37) </b>Describe the ideation stage.</h4>
        </section>
        <section>
          <p>The ideation stage is the generation and initial evaluation of possible product ideas. Ideas may be gathered from users, employees, market research or existing problems and then filtered before detailed development.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>38) </b>Explain the importance of stakeholder feedback.</h4>
        </section>
        <section>
          <p>Stakeholder feedback identifies whether a proposed product meets real user and organisational needs. Feedback can reveal missing requirements or usability problems, allowing changes to be made before release and reducing the risk of an unsuitable product.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>39) </b>Describe the role of design and prototyping.</h4>
        </section>
        <section>
          <p>Design defines the structure, appearance and operation of the proposed product. A prototype provides an early version that can be tested with users, allowing problems to be identified and corrected before full production.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>40) </b>Explain why multiple iterations are used.</h4>
        </section>
        <section>
          <p>Multiple iterations allow a product to be designed, tested, evaluated and improved repeatedly. Feedback and test results from one iteration can be used in the next, improving functionality, usability and quality while reducing development risk.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>41) </b>Define functional requirements.</h4>
        </section>
        <section>
          <p>What a system must do.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>42) </b>Define non-functional requirements.</h4>
        </section>
        <section>
          <p>How a system performs.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>43) </b>Give two functional requirements.</h4>
        </section>
        <section>
          <p>Login and report generation.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>44) </b>Give two non-functional requirements.</h4>
        </section>
        <section>
          <p>Performance speed and security.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>45) </b>Explain why both are important.</h4>
        </section>
        <section>
          <p>Functional requirements describe what the system must do, while non-functional requirements describe qualities or constraints such as speed, security and reliability. Both are necessary because a system must perform the required tasks and also operate to an acceptable standard.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>46) </b>Explain the purpose of testing.</h4>
        </section>
        <section>
          <p>Testing checks whether a system meets its requirements and behaves correctly under expected and unexpected conditions. It can reveal functional errors, performance problems and security weaknesses before users depend on the system.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>47) </b>Describe why bugs must be fixed before release.</h4>
        </section>
        <section>
          <p>Unfixed bugs can cause incorrect results, crashes, security vulnerabilities or data loss. They can prevent users completing tasks and may cause financial, operational or reputational damage, so important defects should be corrected before release.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>48) </b>Explain consequences of inadequate testing.</h4>
        </section>
        <section>
          <p>Inadequate testing can allow faults and security weaknesses to reach users. This may cause crashes, incorrect results, data loss, financial loss and user dissatisfaction. For critical systems, failures can also interrupt important organisational services.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>49) </b>Explain the importance of consistency in manufacturing.</h4>
        </section>
        <section>
          <p>Consistency ensures that products meet the same specifications and quality standards each time they are produced. IT-controlled and automated processes reduce variation and defects, improving reliability and customer confidence.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>50) </b>Describe how IT ensures reliability.</h4>
        </section>
        <section>
          <p>IT improves reliability by automating repeatable processes and continuously monitoring equipment or system performance. Alerts can identify abnormal conditions early, allowing maintenance or corrective action before a major failure occurs.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>51) </b>Explain how IT improves service delivery.</h4>
        </section>
        <section>
          <p>IT can automate service workflows, route requests to the correct staff and provide real-time status information. Online portals and integrated databases also reduce manual data entry and errors, allowing services to be delivered faster and more consistently.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>52) </b>Describe workflow automation.</h4>
        </section>
        <section>
          <p>Workflow automation moves tasks and information through a predefined sequence automatically. For example, a request can be logged, assigned to a responsible employee and escalated if a deadline is missed, reducing manual administration and delays.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>53) </b>Define transaction processing.</h4>
        </section>
        <section>
          <p>Handling business transactions electronically.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>54) </b>Give two TPS examples.</h4>
        </section>
        <section>
          <p>EPOS and banking systems.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>55) </b>Explain why accuracy is important.</h4>
        </section>
        <section>
          <p>TPS must process sales, payments and other transactions accurately because errors can produce incorrect balances, stock records or customer charges. Accurate processing maintains data integrity and prevents financial or operational problems.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>56) </b>Define atomicity.</h4>
        </section>
        <section>
          <p>Transactions complete fully or not at all.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>57) </b>Define consistency.</h4>
        </section>
        <section>
          <p>System remains valid.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>58) </b>Define isolation.</h4>
        </section>
        <section>
          <p>Transactions do not interfere.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>59) </b>Define durability.</h4>
        </section>
        <section>
          <p>Completed transactions are saved.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>60) </b>Explain why ACID properties are important.</h4>
        </section>
        <section>
          <p>ACID properties make transaction processing reliable. Atomicity ensures a transaction is completed fully or not at all; consistency keeps database rules valid; isolation prevents concurrent transactions interfering incorrectly; and durability ensures committed changes survive failures.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>61) </b>Define EPOS.</h4>
        </section>
        <section>
          <p>Electronic system for processing sales.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>62) </b>State two EPOS features.</h4>
        </section>
        <section>
          <p>Barcode scanning and receipts.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>63) </b>Explain how EPOS manages stock.</h4>
        </section>
        <section>
          <p>When a sale is recorded, EPOS can automatically reduce the quantity of the product in the inventory database. The system can generate low-stock alerts or initiate reordering when a defined threshold is reached, giving staff current stock information.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>64) </b>Describe how EPOS improves customer experience.</h4>
        </section>
        <section>
          <p>EPOS speeds up checkout by scanning items and calculating totals automatically. It can apply prices and discounts consistently, process different payment methods and produce receipts, reducing errors and waiting time for customers.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>65) </b>Explain how EPOS supports management decisions.</h4>
        </section>
        <section>
          <p>EPOS records details such as products sold, quantities, prices and times. Managers can analyse these data to identify sales trends and popular products, supporting decisions about stock levels, staffing, pricing and promotions.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>66) </b>Explain the role of financial systems.</h4>
        </section>
        <section>
          <p>Financial systems record and process income, expenditure, invoices, payments, payroll and account balances. They support accurate record-keeping, financial reporting, budgeting, monitoring and management decision-making.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>67) </b>Explain data integrity importance.</h4>
        </section>
        <section>
          <p>Financial information must be accurate and complete because it is used for accounts, payments and business decisions. Validation, access controls, transaction controls and backups help prevent unauthorised or incorrect changes and protect the integrity of financial data.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>68) </b>Explain high availability.</h4>
        </section>
        <section>
          <p>High availability means that an IT service remains accessible when required with minimal downtime. Financial systems may need high availability because interruptions can prevent payments or access to account information. Redundant systems, monitoring and reliable infrastructure can improve availability.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>69) </b>Describe two security measures.</h4>
        </section>
        <section>
          <p>Encryption and access control.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>70) </b>Explain regulatory compliance.</h4>
        </section>
        <section>
          <p>Compliance means following relevant laws, regulations, standards and organisational policies. Financial systems may process sensitive personal and financial information, so compliance controls help protect data and reduce legal, financial and reputational risks.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>71) </b>Define CRM.</h4>
        </section>
        <section>
          <p>System for managing customer relationships.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>72) </b>Explain how CRM stores customer data.</h4>
        </section>
        <section>
          <p>CRM stores customer details, purchases, enquiries, communications and service history in a central database. Authorised staff can retrieve this information during interactions, giving them a more complete view of the customer relationship.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>73) </b>Describe CRM marketing support.</h4>
        </section>
        <section>
          <p>CRM can analyse customer information and buying history to identify trends and suitable customer groups. Marketing teams can use this information to target campaigns, personalise offers and record campaign results for future analysis.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>74) </b>Explain loyalty schemes.</h4>
        </section>
        <section>
          <p>Loyalty schemes reward repeat customers with benefits such as points, discounts or personalised offers. CRM records purchases and rewards automatically, encouraging repeat business while providing useful information about customer buying behaviour.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>75) </b>Describe CRM customer service support.</h4>
        </section>
        <section>
          <p>CRM gives service staff access to customer details and previous interactions such as purchases and complaints. Staff can therefore provide faster, more personalised support without repeatedly asking customers for information.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>76) </b>Explain why customer retention matters.</h4>
        </section>
        <section>
          <p>Customer retention is important because existing customers can provide repeat revenue without the full cost of acquiring new customers. Retained customers may also buy additional products and recommend the organisation, supporting long-term profitability.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>77) </b>Explain upselling and cross-selling using CRM.</h4>
        </section>
        <section>
          <p>Upselling recommends a higher-value or upgraded product, while cross-selling recommends a related additional product. CRM can analyse purchase history and preferences to identify relevant recommendations, increasing sales while making offers more targeted.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>78) </b>Define MIS.</h4>
        </section>
        <section>
          <p>Systems providing management reports.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>79) </b>Explain how MIS supports managers.</h4>
        </section>
        <section>
          <p>MIS collects data from operational systems and processes it into reports, summaries, charts or dashboards. Managers can use this information to monitor performance, identify trends, plan resources and support decisions.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>80) </b>Describe MIS data sources.</h4>
        </section>
        <section>
          <p>MIS can use internal data from systems such as EPOS, accounting, inventory and HR databases. It may also use external data such as market, economic, supplier or competitor information to give managers a wider view.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>81) </b>Explain data processing in MIS.</h4>
        </section>
        <section>
          <p>Data processing in an MIS converts raw operational data into useful information. The system may validate, sort, aggregate and calculate data before presenting it in reports or charts, such as turning individual sales transactions into monthly totals.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>82) </b>Explain dashboard use.</h4>
        </section>
        <section>
          <p>A dashboard presents key performance information visually using summaries, charts and indicators. Managers can quickly see performance against targets and identify trends or exceptions without examining large amounts of raw data.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>83) </b>Define ITS.</h4>
        </section>
        <section>
          <p>Technology-based transport management systems.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>84) </b>Explain sensor use.</h4>
        </section>
        <section>
          <p>Sensors in an ITS collect information such as vehicle position, speed, traffic flow or road conditions. The data can be transmitted to a central system, where it is processed to support traffic control, route planning and transport management.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>85) </b>Explain real-time data importance.</h4>
        </section>
        <section>
          <p>Real-time data shows current transport conditions such as congestion, incidents and delays. An ITS can use this information to update traveller information, change traffic controls or alter routes quickly, improving responsiveness and reducing disruption.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>86) </b>Explain congestion reduction.</h4>
        </section>
        <section>
          <p>ITS can detect congestion using current traffic data and then provide alternative routes, adjust traffic signals or give drivers updated information. These actions can distribute traffic more effectively and reduce delays.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>87) </b>Explain dynamic scheduling.</h4>
        </section>
        <section>
          <p>Dynamic scheduling changes planned routes or schedules in response to current conditions such as traffic, delays or changes in demand. This allows transport resources to be used more efficiently.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>88) </b>Describe demand-responsive scheduling.</h4>
        </section>
        <section>
          <p>Demand-responsive scheduling changes a transport service according to the amount and location of demand. Passenger requests or booking data can be used to decide when and where vehicles operate, reducing unnecessary journeys.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>89) </b>Explain incident management.</h4>
        </section>
        <section>
          <p>Incident-management systems use information from sensors, cameras or reports to detect accidents, breakdowns or road closures. Operators can alert travellers, change traffic controls and provide alternative routes to reduce the impact.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>90) </b>Define location-based services.</h4>
        </section>
        <section>
          <p>Services based on geographic location.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>91) </b>Explain GPS usage.</h4>
        </section>
        <section>
          <p>GPS provides location information that can be used for navigation, route planning and vehicle tracking. Transport operators can use vehicle locations to monitor journeys, estimate arrival times and improve scheduling.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>92) </b>Describe traffic camera role.</h4>
        </section>
        <section>
          <p>Traffic cameras provide images of road conditions, traffic flow and incidents. Operators can use the information to detect congestion or accidents and support traffic management and traveller-information services.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>93) </b>Explain fleet management systems.</h4>
        </section>
        <section>
          <p>Fleet-management systems collect information such as vehicle location, route, mileage, fuel use and maintenance status. Managers can use the information to optimise routes, reduce fuel costs, schedule maintenance and monitor vehicle utilisation.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>94) </b>Define an expert system.</h4>
        </section>
        <section>
          <p>An expert system uses stored expert knowledge and rules to provide advice, identify problems or make recommendations in a specific domain.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>95) </b>State two components.</h4>
        </section>
        <section>
          <p>Two components are the knowledge base, which stores facts and rules, and the inference engine, which applies those rules to reach a conclusion.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>96) </b>Explain the knowledge base.</h4>
        </section>
        <section>
          <p>The knowledge base stores facts, rules and expert knowledge. The inference engine uses this stored knowledge when analysing user input, so the quality and completeness of the knowledge base directly affect the usefulness of the system.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>97) </b>Explain the inference engine.</h4>
        </section>
        <section>
          <p>The inference engine applies rules from the knowledge base to facts supplied by the user or system. It determines which rules are relevant and uses them to reach a diagnosis, identification or recommendation.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>98) </b>State one limitation.</h4>
        </section>
        <section>
          <p>An expert system may give an incorrect or inappropriate result if its knowledge base is incomplete, outdated or the situation falls outside the rules it contains.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>99) </b>Define IT governance.</h4>
        </section>
        <section>
          <p>Framework ensuring IT supports business goals.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>100) </b>Explain its importance.</h4>
        </section>
        <section>
          <p>IT governance aligns IT activities and investment with organisational strategy, establishes responsibilities and controls, manages IT risks and supports compliance and performance monitoring. It helps reduce failures, misuse and poor use of IT resources.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>101) </b>Describe strategic alignment.</h4>
        </section>
        <section>
          <p>Strategic alignment means ensuring that IT plans, systems and investment directly support the organisation&#x27;s business objectives. For example, an organisation aiming to expand online sales needs IT that supports secure, reliable e-commerce and customer services.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>102) </b>Explain IT risk management.</h4>
        </section>
        <section>
          <p>IT risk management identifies threats and weaknesses, assesses their likelihood and impact, and selects controls to reduce them. Controls can include access controls, backups, redundancy, training, monitoring and disaster recovery. Risks should be reviewed because threats and business conditions change.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>103) </b>Explain performance measurement.</h4>
        </section>
        <section>
          <p>Performance measurement compares IT operation with defined targets such as availability, response time, incident frequency, cost or user satisfaction. Monitoring these measures helps managers identify problems and determine whether IT services are meeting organisational requirements.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>104) </b>Describe compliance requirements.</h4>
        </section>
        <section>
          <p>Compliance requirements include relevant laws, regulations, industry standards and organisational policies. They may cover data protection, security, financial records, access, retention and acceptable use. Organisations need controls and records that demonstrate compliance.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>105) </b>Define business continuity.</h4>
        </section>
        <section>
          <p>Ability to continue operations.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>106) </b>Explain why it is essential.</h4>
        </section>
        <section>
          <p>Business continuity is essential because hardware failures, cyberattacks, power failures and natural disasters can interrupt normal operations. A continuity plan identifies critical services and arrangements for continuing them during disruption, reducing downtime, financial loss and customer impact.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>107) </b>Define disaster recovery.</h4>
        </section>
        <section>
          <p>Restoring systems after failure.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>108) </b>Explain backup importance.</h4>
        </section>
        <section>
          <p>Backups provide copies of important data that can be restored after accidental deletion, corruption, hardware failure or cyberattack. Regular, tested and protected backups are therefore a key part of disaster recovery and business continuity.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>109) </b>Define risk management.</h4>
        </section>
        <section>
          <p>Identifying and controlling threats.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>110) </b>Explain why risks can&#x27;t be eliminated.</h4>
        </section>
        <section>
          <p>Risks cannot be completely eliminated because IT systems depend on hardware, software, people, networks and external services that may fail or be attacked. New threats can also appear and every control has limitations. Risk management therefore aims to reduce risk to an acceptable level.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>111) </b>Explain risk reduction vs avoidance.</h4>
        </section>
        <section>
          <p>Risk reduction keeps an activity but introduces controls to lower the likelihood or impact of the risk, such as encryption or backups. Risk avoidance changes or stops the activity so that the particular risk no longer occurs. Avoidance can remove a risk but may also remove the benefits of the activity.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>112) </b>Define Acceptable Use Policy (AUP).</h4>
        </section>
        <section>
          <p>Rules for IT system usage.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>113) </b>Explain why AUPs are needed.</h4>
        </section>
        <section>
          <p>AUPs establish clear rules for safe and appropriate use of organisational IT resources. They help reduce risks such as malware, unauthorised access and misuse of data, and make users aware of their responsibilities and possible consequences of breaking the rules.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>114) </b>Describe two AUP rules.</h4>
        </section>
        <section>
          <p>Two rules could be: users must keep passwords confidential and must not share login credentials; and users must not install unauthorised software or connect unauthorised devices to the organisation&#x27;s network.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>115) </b>Define phased changeover.</h4>
        </section>
        <section>
          <p>Gradual system replacement.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>116) </b>One advantage.</h4>
        </section>
        <section>
          <p>Phased changeover reduces risk because the new system is introduced and tested in manageable stages. Problems can be corrected before the next stage is introduced, although the complete changeover can take longer.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>117) </b>One disadvantage.</h4>
        </section>
        <section>
          <p>Phased changeover can take longer and may require the organisation to support different stages or versions of the system during the transition.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>118) </b>Define direct changeover.</h4>
        </section>
        <section>
          <p>Immediate system replacement.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>119) </b>One advantage.</h4>
        </section>
        <section>
          <p>Direct changeover can be implemented quickly because the old system is stopped and the new system starts immediately, avoiding the cost of operating both systems for a long period.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>120) </b>One disadvantage.</h4>
        </section>
        <section>
          <p>Direct changeover has a high risk because if the new system fails, there may be no functioning old system available to continue operations.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>121) </b>Define parallel changeover.</h4>
        </section>
        <section>
          <p>Old and new systems run together.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>122) </b>Explain risk reduction.</h4>
        </section>
        <section>
          <p>Parallel changeover reduces risk because the old system remains available while the new system is tested. If the new system fails or produces incorrect results, staff can continue using the old system.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>123) </b>Explain high cost.</h4>
        </section>
        <section>
          <p>Parallel changeover is expensive because the organisation must operate and support both systems at the same time. This can involve duplicate hardware, software, support and staff time.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>124) </b>Define pilot changeover.</h4>
        </section>
        <section>
          <p>Testing system in one area.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>125) </b>Explain suitable situations.</h4>
        </section>
        <section>
          <p>Pilot changeover is suitable for a large organisation or a system affecting many users or locations. The new system can first be tested in one department, branch or user group, allowing problems and feedback to be addressed before wider deployment.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>126) </b>Define system maintenance.</h4>
        </section>
        <section>
          <p>System maintenance is the ongoing process of modifying, updating and supporting an IT system so that it remains secure, reliable, usable and suitable for its intended purpose.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>127) </b>Describe perfective maintenance.</h4>
        </section>
        <section>
          <p>Perfective maintenance improves an existing system without changing its fundamental purpose. Examples include improving performance, usability or the user interface and adding enhancements requested by users.</p>
        </section>
      </section>

      <section>
        <section>
          <h4><b>128) </b>Describe adaptive maintenance.</h4>
        </section>
        <section>
          <p>Adaptive maintenance changes an existing system so that it continues to work when its environment changes, for example after a new operating system, hardware platform, regulation or business process is introduced.</p>
        </section>
      </section>
      </div>
  </div>

  <script src="<?= $pptBase ?>/lib/js/head.min.js"></script>
  <script src="<?= $pptBase ?>/js/reveal.min.js"></script>
  <script>
    Reveal.initialize({
      controls: true,
      progress: true,
      history: true,
      center: true,
      theme: Reveal.getQueryHash().theme,
      transition: Reveal.getQueryHash().transition || 'default',
      dependencies: [
        { src: '<?= $pptBase ?>/lib/js/classList.js', condition: function() { return !document.body.classList; } },
        { src: '<?= $pptBase ?>/plugin/markdown/marked.js', condition: function() { return !!document.querySelector('[data-markdown]'); } },
        { src: '<?= $pptBase ?>/plugin/markdown/markdown.js', condition: function() { return !!document.querySelector('[data-markdown]'); } },
        { src: '<?= $pptBase ?>/plugin/highlight/highlight.js', async: true, callback: function() { hljs.initHighlightingOnLoad(); } },
        { src: '<?= $pptBase ?>/plugin/zoom-js/zoom.js', async: true, condition: function() { return !!document.body.classList; } }
      ]
    });
  </script>
</body>
</html>
