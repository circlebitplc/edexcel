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

	<meta name='description' content='Topic 13 – Enabling Technologies'>
	<meta name='author' content='Enidu Batuwanthudawe'>

	<meta name='apple-mobile-web-app-capable' content='yes' />
	<meta name='apple-mobile-web-app-status-bar-style' content='black-translucent' />

	<meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no'>

	<link rel='stylesheet' href='<?= $pptBase ?>/css/reveal.min.css'>
	<link rel='stylesheet' href='<?= $pptBase ?>/css/theme/default.css' id='theme'>
	<link rel='stylesheet' href='<?= $pptBase ?>/css/custom.css'>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

	<!-- For syntax highlighting -->
	<link rel='stylesheet' href='<?= $pptBase ?>/lib/css/zenburn.css'>

	<!-- If the query includes 'print-pdf', use the PDF print sheet -->
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
					<h4>Topic 13</h4>
					<h2> Enabling Technologies</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
<section>
  <h3>SECTION A – Knowledge and Understanding</h3>
</section>

<section>
  <section>
    <h4><b>1) </b>Define the term virtualisation.</h4>
  </section>
  <section>
    <p>Virtualisation is the use of software to create virtual versions of physical computing resources such as servers, storage or operating systems. It allows multiple virtual systems to run on a single physical machine by sharing hardware resources. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>2) </b>State two types of virtualisation.</h4>
  </section>
  <section>
    <p>Two types of virtualisation are server virtualisation and desktop virtualisation. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>3) </b>Identify one example of desktop virtualisation.</h4>
  </section>
  <section>
    <p>An example of desktop virtualisation is Virtual Desktop Infrastructure (VDI). [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>4) </b>What is meant by a virtual machine (VM)?</h4>
  </section>
  <section>
    <p>A virtual machine is a software-based emulation of a physical computer that runs its own operating system and applications. It operates independently while sharing the physical hardware of the host machine. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>5) </b>State one function of a hypervisor.</h4>
  </section>
  <section>
    <p>A hypervisor manages and allocates hardware resources to virtual machines. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>6) </b>Define the term containerisation.</h4>
  </section>
  <section>
    <p>Containerisation is a method of deploying applications where software and its dependencies are packaged together in containers. Containers share the host operating system kernel while remaining isolated from each other. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>7) </b>State one difference between a container and a virtual machine.</h4>
  </section>
  <section>
    <p>A container shares the host operating system, whereas a virtual machine runs its own operating system. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>8) </b>Define a distributed system.</h4>
  </section>
  <section>
    <p>A distributed system is a collection of independent computers that work together over a network. To users, the system appears as a single unified system. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>9) </b>Identify two examples of distributed systems.</h4>
  </section>
  <section>
    <p>Two examples of distributed systems are cloud computing platforms and distributed databases. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>10) </b>What is meant by fault tolerance?</h4>
  </section>
  <section>
    <p>Fault tolerance is the ability of a system to continue operating correctly even when one or more components fail. This is achieved through redundancy and backup mechanisms. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>11) </b>State one reason why replication is used in distributed systems.</h4>
  </section>
  <section>
    <p>Replication is used to improve system reliability. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>12) </b>Define the term Human Computer Interaction (HCI).</h4>
  </section>
  <section>
    <p>Human Computer Interaction is the study of how users interact with computer systems. It focuses on designing interfaces that are efficient, usable and user-friendly. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>13) </b>What is meant by usability?</h4>
  </section>
  <section>
    <p>Usability refers to how easy and efficient a system is for users to learn and use. A usable system allows users to complete tasks accurately with minimal effort. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>14) </b>State two usability goals in HCI.</h4>
  </section>
  <section>
    <p>Two usability goals are efficiency and learnability. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>15) </b>Define accessibility in the context of HCI.</h4>
  </section>
  <section>
    <p>Accessibility refers to designing computer systems so they can be used by people with disabilities. This includes visual, auditory, motor and cognitive impairments. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>16) </b>State one example of a visual HCI element.</h4>
  </section>
  <section>
    <p>An example of a visual HCI element is an icon. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>17) </b>State one example of an audio HCI element.</h4>
  </section>
  <section>
    <p>An example of an audio HCI element is a notification sound. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>18) </b>State one example of a haptic HCI element.</h4>
  </section>
  <section>
    <p>An example of a haptic HCI element is vibration feedback. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>19) </b>What is meant by ergonomics?</h4>
  </section>
  <section>
    <p>Ergonomics is the study of designing systems and workplaces to suit human physical capabilities. It aims to reduce discomfort and prevent injury. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>20) </b>Define the term firmware.</h4>
  </section>
  <section>
    <p>Firmware is low-level software permanently stored in non-volatile memory. It controls how hardware devices operate and communicate with the operating system. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>21) </b>State two components commonly found in firmware.</h4>
  </section>
  <section>
    <p>Two components commonly found in firmware are the BIOS/UEFI and device drivers. [2]</p>
  </section>
</section>

<section>
  <h3>SECTION B – Application and Explanation</h3>
</section>

<section>
  <section>
    <h4><b>22) </b>Explain two benefits of using virtualisation in organisations.</h4>
  </section>
  <section>
    <p>Virtualisation allows multiple virtual machines to run on a single physical server, which reduces hardware costs by minimising the number of physical devices required. It also improves resource utilisation, as processing power, memory and storage can be allocated dynamically based on demand, increasing efficiency. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>23) </b>Explain two limitations of using virtualisation.</h4>
  </section>
  <section>
    <p>One limitation of virtualisation is performance overhead, as virtual machines share physical hardware, which can reduce performance during high demand. Another limitation is increased complexity, since managing virtual environments requires specialised skills and careful configuration. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>24) </b>Explain how a hypervisor manages virtual machines.</h4>
  </section>
  <section>
    <p>A hypervisor creates and controls virtual machines by allocating hardware resources such as CPU, memory and storage. It isolates each virtual machine to prevent interference and ensures resources are distributed fairly, maintaining system stability and performance. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>25) </b>Explain the difference between containerisation and virtualisation.</h4>
  </section>
  <section>
    <p>Virtualisation runs multiple operating systems on a single physical machine using virtual machines, each with its own OS. Containerisation runs applications in isolated containers that share the host operating system, making containers lighter, faster and more resource-efficient. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>26) </b>Explain two benefits of using containerisation.</h4>
  </section>
  <section>
    <p>One benefit of containerisation is faster deployment, as containers include all required dependencies. Another benefit is portability, because containers can run consistently across different environments without modification. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>27) </b>Explain how distributed systems improve system reliability.</h4>
  </section>
  <section>
    <p>Distributed systems use multiple connected computers to perform tasks. If one node fails, other nodes continue operating, ensuring availability. Replication and redundancy allow data and services to remain accessible, improving fault tolerance. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>28) </b>Explain how fault tolerance is achieved in distributed systems.</h4>
  </section>
  <section>
    <p>Fault tolerance is achieved through redundancy, where multiple components perform the same task. Replication ensures copies of data exist across nodes, allowing the system to continue functioning even when failures occur. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>29) </b>Explain the purpose of replication in distributed databases.</h4>
  </section>
  <section>
    <p>Replication creates multiple copies of data across different servers. This improves availability, ensures faster access for users in different locations, and allows data recovery if a server fails. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>30) </b>Explain two challenges of managing distributed systems.</h4>
  </section>
  <section>
    <p>One challenge is network dependency, as system performance relies heavily on network reliability. Another challenge is data consistency, since keeping replicated data synchronised across nodes can be complex. [4]</p>
  </section>
</section>

<section>
  <h3>SECTION C – Scenario-Based Questions</h3>
</section>

<section>
  <section>
    <h4><b>37) </b>A company plans to replace several physical servers with a virtualised environment. Explain two advantages and two disadvantages of using virtualisation in this situation.</h4>
  </section>
  <section>
    <p>Virtualisation would reduce hardware costs because multiple virtual machines can run on a single physical server, meaning fewer physical servers are required. This also lowers energy and maintenance costs. Another advantage is improved resource utilisation, as processing power, memory and storage can be allocated dynamically based on demand.
However, one disadvantage is performance overhead, since virtual machines share the same physical hardware, which may reduce performance during peak usage. Another disadvantage is increased management complexity, as specialised skills are required to configure, monitor and secure the virtual environment. [6]</p>
  </section>
</section>

 <section>
  <section>
    <h4><b>38) </b>An organisation uses containerisation to deploy its applications across different platforms. Explain why containerisation is suitable for this purpose.</h4>
  </section>
  <section>
    <p>Containerisation is suitable because containers package applications together with their dependencies, ensuring the software runs consistently across different platforms. This improves portability, as containers can be deployed on development, testing and production systems without modification.
In addition, containers are lightweight because they share the host operating system kernel, allowing faster startup times and efficient use of system resources. This makes application deployment quicker and more scalable. [6]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>39) </b>A global company uses a distributed database system across several countries. Explain how this system improves availability and performance.</h4>
  </section>
  <section>
    <p>A distributed database improves availability by storing data across multiple servers in different locations. If one server fails, other servers can continue providing access to the data, ensuring minimal downtime.
Performance is improved because users can access data from the nearest server, reducing network latency. Replication also balances the workload across servers, preventing bottlenecks during high demand. [6]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>40) </b>A hospital requires its computer systems to continue operating even if a server fails. Explain how fault tolerance can be achieved in this system.</h4>
  </section>
  <section>
    <p>Fault tolerance can be achieved by using redundant servers so that if one server fails, another can immediately take over. Data replication ensures patient records are available on multiple servers, preventing data loss.
Load balancing distributes processing tasks across multiple servers, reducing the impact of failure. Regular backups also allow systems to be restored quickly if faults occur, ensuring continuous operation. [6]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>41) </b>A software company is redesigning its user interface to improve usability. Explain how HCI principles can be applied to achieve this.</h4>
  </section>
  <section>
    <p>HCI principles focus on designing interfaces that match user needs and behaviour. Using consistent layouts and familiar icons helps users learn the system quickly. Clear feedback, such as confirmation messages, reduces user errors.
Accessibility features such as adjustable font sizes and keyboard navigation improve usability for a wider range of users. Testing the interface with real users allows designers to identify and correct usability problems early. [6]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>42) </b>An online banking system must be accessible to users with disabilities. Explain how accessibility features can be implemented to meet this requirement.</h4>
  </section>
  <section>
    <p>Accessibility can be improved by providing screen reader compatibility for visually impaired users. High-contrast colour schemes and resizable text help users with visual difficulties.
Keyboard navigation allows users with motor impairments to operate the system without a mouse. Audio cues and captions support users with hearing impairments, ensuring inclusive access. [6]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>43) </b>A company stores critical data in its firmware. Explain why firmware is essential in this scenario.</h4>
  </section>
  <section>
    <p>Firmware controls how hardware components operate and communicate with the operating system. Without firmware, the hardware would not initialise correctly, preventing the system from starting.
Firmware also stores essential instructions required for system boot-up and device control. Because firmware is stored in non-volatile memory, it remains available even when the system is powered off, ensuring reliable operation. [6]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>44) </b>A manufacturing company uses embedded systems controlled by firmware. Explain the importance of updating firmware regularly.</h4>
  </section>
  <section>
    <p>Regular firmware updates fix bugs and security vulnerabilities that could otherwise be exploited. Updates also improve system stability and performance.
Firmware updates can add new features or support newer hardware components. This ensures embedded systems remain reliable, secure and compatible with modern technologies. [6]</p>
  </section>
</section>

<section>
  <h3>SECTION D – Evaluation and Extended Response</h3>
</section>

<section>
  <section>
    <h4><b>45) </b>Evaluate the use of virtualisation compared to using physical servers in organisations.</h4>
  </section>
  <section>
    <p>Virtualisation offers several advantages over using physical servers. One major advantage is cost reduction, as multiple virtual machines can run on a single physical server, reducing hardware, electricity and maintenance costs. Virtualisation also improves resource utilisation, since CPU, memory and storage can be allocated dynamically according to demand. In addition, virtual machines can be created, backed up and restored quickly, improving flexibility and disaster recovery.
However, virtualisation also has disadvantages. Performance can be reduced if many virtual machines share the same physical hardware, especially during peak usage. Virtual environments are also more complex to manage and require skilled administrators to configure, monitor and secure the systems properly. Security risks may increase if the hypervisor is compromised, as multiple virtual machines could be affected.
Overall, virtualisation is more suitable for organisations that require scalability, flexibility and cost efficiency. For smaller organisations with low workloads or limited technical expertise, physical servers may still be more appropriate. [10]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>46) </b>Evaluate the effectiveness of containerisation compared to virtualisation for application deployment.</h4>
  </section>
  <section>
    <p>Containerisation is highly effective for application deployment because containers are lightweight and share the host operating system kernel. This results in faster startup times, efficient use of resources and easier scalability. Containers also improve portability, as applications can run consistently across different platforms without modification.
However, unlike virtual machines, containers do not provide full operating system isolation. This can increase security risks if a container is compromised. Managing large numbers of containers can also be challenging without proper orchestration tools.
Virtualisation, on the other hand, offers stronger isolation since each virtual machine runs its own operating system. This improves security but increases resource usage and startup time.
In conclusion, containerisation is more effective for modern, scalable application deployment, while virtualisation is better suited for running multiple operating systems securely. [10]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>47) </b>Evaluate the importance of distributed systems for large-scale organisations.</h4>
  </section>
  <section>
    <p>Distributed systems are important for large-scale organisations because they improve reliability, availability and performance. By spreading data and processing across multiple servers, organisations can continue operating even if one system fails. Replication ensures data remains accessible, improving fault tolerance.
Distributed systems also improve performance by allowing users to access data from nearby servers, reducing network latency. Scalability is another advantage, as additional nodes can be added to handle increased demand.
However, distributed systems are complex to manage. Network dependency means performance can be affected by connectivity issues. Maintaining data consistency across multiple nodes is also challenging and may require complex synchronisation methods.
Overall, despite the challenges, distributed systems are essential for large organisations that require high availability, scalability and global access. [10]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>48) </b>Evaluate how Human Computer Interaction (HCI) principles improve the effectiveness of computer systems.</h4>
  </section>
  <section>
    <p>HCI principles improve system effectiveness by ensuring interfaces are designed around user needs and behaviour. Good HCI improves usability, making systems easier to learn and reducing user errors. This increases productivity and user satisfaction.
Accessibility is another key benefit, as HCI principles ensure systems can be used by people with disabilities. Ergonomic design reduces physical strain, improving long-term use.
However, implementing effective HCI can be time-consuming and costly, requiring user research, testing and redesign. Poorly implemented HCI may also result in cluttered interfaces.
In conclusion, HCI principles significantly improve system effectiveness, and the benefits usually outweigh the costs, especially for systems with large user bases. [10]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>49) </b>Evaluate the role of firmware in modern computer systems.</h4>
  </section>
  <section>
    <p>Firmware plays a critical role by controlling how hardware components operate and interact with the operating system. It enables system boot-up and ensures hardware devices function correctly.
Firmware is reliable because it is stored in non-volatile memory and remains available even when the system is powered off. Regular firmware updates improve security, performance and compatibility.
However, updating firmware carries risks, as failed updates can render devices unusable. Firmware errors are also harder to fix than software errors.
Overall, firmware is essential for system operation, and careful management is required to maintain reliability and security. [8]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>50) </b>Evaluate the importance of accessibility in HCI design.</h4>
  </section>
  <section>
    <p>Accessibility is important because it ensures systems can be used by people with disabilities, promoting inclusivity. Accessible systems improve usability for all users, not just those with impairments.
Features such as screen readers, keyboard navigation and adjustable text sizes improve user experience. Accessibility also helps organisations comply with legal requirements.
However, designing accessible systems requires additional time and resources. Some features may increase interface complexity.
In conclusion, accessibility is essential in modern HCI design, and its benefits outweigh the challenges. [8]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>51) </b>A multinational organisation plans to modernise its IT infrastructure using virtualisation, containerisation and distributed systems. Evaluate how these technologies together can improve efficiency and reliability.</h4>
  </section>
  <section>
    <p>Virtualisation can improve efficiency by allowing multiple virtual machines to run on a single physical server, reducing hardware costs, power consumption and physical space requirements. Resources such as CPU and memory can be allocated dynamically, ensuring better utilisation and flexibility. Virtual machines can also be backed up and restored quickly, improving disaster recovery.
Containerisation further improves efficiency by packaging applications and their dependencies into lightweight containers. Containers start faster than virtual machines and use fewer system resources, allowing applications to be deployed and scaled rapidly across different environments. This improves consistency and reduces deployment errors.
Distributed systems improve reliability by spreading data and processing across multiple servers in different locations. Replication ensures that data remains available even if one server fails, while load balancing distributes workloads to prevent bottlenecks and improve performance for global users.
However, using these technologies together increases system complexity. Skilled staff are required to manage virtual machines, containers and distributed nodes securely. Network dependency may also affect performance if connectivity issues occur.
Overall, when managed correctly, the combined use of virtualisation, containerisation and distributed systems significantly improves efficiency, scalability and reliability for multinational organisations, making them well suited for modern large-scale IT infrastructures. [14]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>52) </b>Evaluate the impact of poor Human Computer Interaction (HCI) design on the effectiveness of computer systems.</h4>
  </section>
  <section>
    <p>Poor HCI design can significantly reduce system effectiveness by making systems difficult to learn and use. Users may struggle to understand interfaces, increasing errors and reducing productivity. Complex or inconsistent layouts can cause frustration and slow task completion.
Accessibility issues further reduce effectiveness, as users with disabilities may be unable to use the system at all. This limits inclusivity and may lead to legal consequences for organisations that fail to meet accessibility standards.
Poor ergonomics can also cause physical strain, fatigue or injury, particularly for systems used for long periods. This can result in decreased user satisfaction and increased absenteeism.
However, improving HCI requires time and resources, including user testing and redesign. Despite this, the long-term benefits of improved usability, reduced errors and higher productivity outweigh the initial costs.
In conclusion, poor HCI design has a major negative impact on system effectiveness, and investing in good HCI is essential for successful computer systems. [12]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>53) </b>A healthcare organisation stores patient data using distributed databases and cloud-based systems. Evaluate the ethical, legal and reliability issues involved.</h4>
  </section>
  <section>
    <p>Distributed and cloud-based systems improve reliability by replicating patient data across multiple servers, ensuring availability even during hardware failures. This is critical in healthcare environments where access to data can be life-saving.
However, ethical issues arise regarding patient privacy and consent. Sensitive medical data may be accessed by unauthorised individuals if security controls are weak. There is also a risk of data being used beyond its intended purpose, violating patient trust.
Legal issues include compliance with data protection laws such as GDPR, which require organisations to protect personal data and control where it is stored and processed. Failure to comply can result in severe penalties.
Reliability depends heavily on network connectivity. System outages or network failures can temporarily prevent access to patient records, which may affect patient care.
Overall, while distributed and cloud-based systems offer significant reliability benefits, strong security measures, legal compliance and ethical data handling practices are essential to minimise risks. [14]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>54) </b>Evaluate the importance of firmware updates in maintaining secure and reliable computer systems.</h4>
  </section>
  <section>
    <p>Firmware updates are important because they fix security vulnerabilities that could be exploited by attackers. Since firmware operates at a low level, vulnerabilities can give attackers deep system access if left unpatched.
Updates also improve system reliability by fixing bugs that may cause hardware malfunctions or system crashes. Firmware updates can enhance performance and compatibility with newer hardware or software.
However, updating firmware carries risks. If an update fails or is interrupted, the device may become unusable. Firmware updates also require careful testing, as errors are harder to correct than software issues.
Despite these risks, regular firmware updates are essential. The security and reliability benefits outweigh the potential drawbacks when updates are managed carefully. [12]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>55) </b>Evaluate how enabling technologies support scalability in modern computer systems.</h4>
  </section>
  <section>
    <p>Enabling technologies such as virtualisation, containerisation and distributed systems support scalability by allowing systems to grow without major redesign. Virtualisation enables additional virtual machines to be created quickly as demand increases.
Containerisation allows applications to scale horizontally by deploying more containers when workloads increase. Containers can be started and stopped rapidly, supporting elastic scaling.
Distributed systems further support scalability by allowing new nodes to be added to share processing and storage loads. This prevents performance degradation during high demand.
However, scaling systems increases management complexity and requires monitoring, automation and skilled administrators. Network limitations may also affect scalability.
In conclusion, enabling technologies are essential for scalable modern systems, and their benefits far outweigh the challenges when implemented correctly. [14]</p>
  </section>
</section>

	 
 

			</div>
		</div>

		<script src="<?= $pptBase ?>/lib/js/head.min.js"></script>
		<script src="<?= $pptBase ?>/js/reveal.min.js"></script>

		<script>

			// Full list of configuration options available here:
			// https://github.com/hakimel/reveal.js#configuration
			Reveal.initialize({
				controls: true,
				progress: true,
				history: true,
				center: true,

				theme: Reveal.getQueryHash().theme, // available themes are in /css/theme
				transition: Reveal.getQueryHash().transition || 'default', // default/cube/page/concave/zoom/linear/fade/none

				// Parallax scrolling
				// parallaxBackgroundImage: 'https://s3.amazonaws.com/hakim-static/reveal-js/reveal-parallax-1.jpg',
				// parallaxBackgroundSize: '2100px 900px',

				// Optional libraries used to extend on reveal.js
				dependencies: [
					{ src: '<?= $pptBase ?>/lib/js/classList.js', condition: function() { return !document.body.classList; } },
					{ src: '<?= $pptBase ?>/plugin/markdown/marked.js', condition: function() { return !!document.querySelector( '[data-markdown]' ); } },
					{ src: '<?= $pptBase ?>/plugin/markdown/markdown.js', condition: function() { return !!document.querySelector( '[data-markdown]' ); } },
					{ src: '<?= $pptBase ?>/plugin/highlight/highlight.js', async: true, callback: function() { hljs.initHighlightingOnLoad(); } },
					{ src: '<?= $pptBase ?>/plugin/zoom-js/zoom.js', async: true, condition: function() { return !!document.body.classList; } },
					
				]
			});

		</script>

	</body>
</html>

