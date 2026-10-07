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

	<meta name='description' content='Topic 14 – using it systems in organization'>
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
					<h4>Topic 14</h4>
					<h2>using it systems in organization </h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>

	 <section>
  <h3>SECTION B – Automation, Monitoring & Control Systems</h3>
</section>

<section>
  <section>
    <h4><b>11) </b>Define the term automation.</h4>
  </section>
  <section>
    <p>Automation is the use of computer-controlled systems, machines, and software to perform tasks automatically with minimal or no human intervention. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>12) </b>Explain why organizations use automated systems.</h4>
  </section>

  <section>
    <p>Organisations use automated systems to improve efficiency, accuracy, and reliability in their operations. [4]</p>
  </section>

  <section>
    <p>One key reason is to increase productivity. Automated systems can perform tasks faster than humans and operate continuously without breaks, allowing organisations to produce more output in less time and reduce operational delays.</p>
  </section>

  <section>
    <p>Another reason is to reduce human error and improve accuracy. Automated systems follow programmed instructions consistently, which improves the quality of products and services and reduces mistakes in tasks such as manufacturing, data processing, and calculations.</p>
  </section>

  <section>
    <p>Organisations also use automation to reduce costs in the long term. Although initial setup costs may be high, automation lowers labour costs, reduces waste, and improves efficiency over time.</p>
  </section>

  <section>
    <p>In addition, automated systems can improve safety by performing dangerous or repetitive tasks that would otherwise put human workers at risk.</p>
  </section>

  <section>
    <p>Overall, automated systems help organisations operate more efficiently, competitively, and reliably.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>13) </b>Explain how automation improves accuracy in organizations.</h4>
  </section>

  <section>
    <p>Automation improves accuracy in organisations by reducing human error and ensuring tasks are performed consistently. [3]</p>
  </section>

  <section>
    <p>Automated systems follow programmed instructions and predefined rules, which means tasks such as data entry, calculations, manufacturing, and quality checks are carried out in the same way every time. This eliminates mistakes caused by fatigue, distraction, or misjudgement.</p>
  </section>

  <section>
    <p>Automation also uses sensors and control systems to measure values precisely and make real-time adjustments. For example, in manufacturing, automated machines can measure exact dimensions and correct errors immediately, ensuring products meet required standards.</p>
  </section>

  <section>
    <p>As a result, automation increases reliability, improves product and service quality, and reduces costly errors across organisational processes.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>14) </b>Explain two advantages of using automation in organizations.</h4>
  </section>

  <section>
    <p><b>Increased Efficiency and Productivity</b></p>
  </section>

  <section>
    <p>Automation allows tasks to be performed faster and continuously without fatigue. Machines and automated systems can operate 24/7, increasing output and reducing the time needed to complete processes. This leads to higher productivity and improved operational efficiency.</p>
  </section>

  <section>
    <p><b>Improved Accuracy and Consistency</b></p>
  </section>

  <section>
    <p>Automated systems perform tasks in the same way every time, reducing human error. This ensures consistent quality in production and services, which is especially important in areas such as manufacturing, data processing, and quality control. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>15) </b>Explain two disadvantages of using automation in organizations.</h4>
  </section>

  <section>
    <p><b>High Implementation and Maintenance Costs</b></p>
  </section>

  <section>
    <p>Automation systems require expensive equipment such as robots, sensors, control software, and specialised machinery. Organisations must also spend money on installation, system upgrades, maintenance, and staff training. These high costs can be difficult to justify, especially for small or medium-sized organisations.</p>
  </section>

  <section>
    <p><b>Job Losses and Reduced Workforce Flexibility</b></p>
  </section>

  <section>
    <p>Automation can replace human workers in repetitive or routine tasks, leading to job losses and reduced employment opportunities. In addition, automated systems are designed for specific tasks and may not adapt easily to unexpected situations, reducing flexibility compared to human workers. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>16) </b>Define the term monitoring system.</h4>
  </section>
  <section>
    <p>A monitoring system is a system that collects, records, and displays data about processes, equipment, or environments so that performance and conditions can be observed and analysed. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>17) </b>Explain how monitoring systems are used in organizations.</h4>
  </section>

  <section>
    <p>Monitoring systems are used in organisations to observe, collect, and report data about processes, equipment, and environments so that performance can be tracked and problems identified early. [4]</p>
  </section>

  <section>
    <p>Monitoring systems use sensors and data-collection devices to measure factors such as temperature, pressure, machine performance, energy usage, or system availability. The collected data is displayed on dashboards, stored in logs, or sent as alerts to staff.</p>
  </section>

  <section>
    <p>By continuously monitoring conditions, organisations can detect faults, inefficiencies, or abnormal behaviour before they cause serious problems. For example, monitoring systems can warn staff if a server is overheating, a machine is underperforming, or energy usage is unusually high.</p>
  </section>

  <section>
    <p>Overall, monitoring systems help organisations maintain reliability, improve efficiency, enhance safety, and support informed decision-making by providing accurate, real-time information.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>18) </b>Define the term control system.</h4>
  </section>
  <section>
    <p>A control system is a system that uses sensors, processing, and actuators to automatically regulate and adjust a process or machine, ensuring it operates within preset limits or achieves a desired output. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>19) </b>Explain the difference between monitoring systems and control systems.</h4>
  </section>

  <section>
    <p>The difference between monitoring systems and control systems lies in what they do with the data they collect. [4]</p>
  </section>

  <section>
    <p>Monitoring systems are used to observe and record data about a process or environment. They collect information from sensors and display or log it for human operators to review. Monitoring systems do not take action themselves.</p>
  </section>

  <section>
    <p>Control systems use sensor data to automatically make adjustments to a process. They compare actual values with preset target values and send signals to actuators to correct differences without human intervention.</p>
  </section>

  <section>
    <p>In summary, monitoring systems observe and report, while control systems observe, decide, and act automatically.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>20) </b>Explain how sensors are used in automation systems.</h4>
  </section>

  <section>
    <p>Sensors are used in automation systems to detect changes in physical conditions and provide input data to the system for automatic operation. [4]</p>
  </section>

  <section>
    <p>Sensors measure physical variables such as temperature, pressure, light, speed, position, or motion and convert these into electrical signals sent to a controller.</p>
  </section>

  <section>
    <p>The controller analyses the sensor data and triggers actuators or control devices to adjust machine operation.</p>
  </section>

  <section>
    <p>By providing accurate, real-time data, sensors allow automation systems to operate efficiently, maintain quality, improve safety, and respond quickly to changes.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>21) </b>Explain how actuators are used in control systems.</h4>
  </section>

  <section>
    <p>Actuators are used in control systems to carry out physical actions based on signals from the controller. [4]</p>
  </section>

  <section>
    <p>The controller processes sensor data and sends electrical signals to actuators.</p>
  </section>

  <section>
    <p>The actuator converts these signals into physical actions such as opening valves, moving robotic arms, adjusting motor speed, or switching machines on or off.</p>
  </section>

  <section>
    <p>Actuators allow control systems to regulate processes accurately and automatically without constant human intervention.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>22) </b>Explain the importance of feedback in control systems.</h4>
  </section>

  <section>
    <p>Feedback is important in control systems because it allows the system to monitor its output and make automatic adjustments to maintain desired performance. [4]</p>
  </section>

  <section>
    <p>Sensors collect output data and send it back to the controller as feedback.</p>
  </section>

  <section>
    <p>The controller compares actual output with target values and makes corrections automatically.</p>
  </section>

  <section>
    <p>Feedback ensures accuracy, stability, safety, and effective response to changes without human intervention.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>23) </b>Explain how automation, monitoring and control systems work together in a manufacturing environment.</h4>
  </section>

  <section>
    <p>Automation, monitoring, and control systems work together in a manufacturing environment to ensure that production is efficient, accurate, and consistent. [6]</p>
  </section>

  <section>
    <p>Automation systems perform production tasks using machines, robots, and PLCs, increasing speed and reducing human error.</p>
  </section>

  <section>
    <p>Monitoring systems use sensors to collect real-time data about machine performance and conditions.</p>
  </section>

  <section>
    <p>Control systems use this data to make automatic adjustments to maintain safe and efficient operation.</p>
  </section>

  <section>
    <p>Together, automation performs the work, monitoring checks performance, and control systems adjust processes, improving quality, safety, and efficiency.</p>
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

