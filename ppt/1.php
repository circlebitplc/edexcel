<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'><head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 
 <section>
  <section><h3>Question 1</h3></section>

  <section>
    <section><h4><b>1(a)</b> Which one of the following is primarily responsible for executing program instructions? [1]</h4></section>
    <section><p><b>Answer:</b> CPU</p></section>
  </section>

  <section>
    <section><h4><b>1(b)</b> Which type of memory is non-volatile? [1]</h4></section>
    <section><p><b>Answer:</b> ROM</p></section>
  </section>

  <section>
    <section><h4><b>1(c)</b> Explain why solid-state storage is important for use in spacecraft during space missions. [2]</h4></section>
    <section><p>Solid-state storage has no moving parts, making it more durable and resistant to vibration during launch and space travel. It is also faster and more reliable, reducing the risk of data loss in critical missions.</p></section>
  </section>

  <section>
    <section><h4><b>1(d)(i)</b> Explain one advantage of using embedded systems in spacecraft. [2]</h4></section>
    <section><p>Embedded systems are dedicated to a specific task, so they are more efficient and reliable. This ensures real-time processing, which is essential for spacecraft operations.</p></section>
  </section>

  <section>
    <section><h4><b>1(d)(ii)</b> Explain one disadvantage of embedded systems. [2]</h4></section>
    <section><p>Embedded systems are difficult to modify or upgrade because they are built for a single purpose. If the system fails, it may require complete replacement rather than repair.</p></section>
  </section>

  <section>
    <section><h4><b>1(e)(i)</b> A storage device has a capacity of 256 GiB. Each file has a size of 8 MiB. Construct an expression and show your working to calculate the number of files stored. [2]</h4></section>
    <section><p>256 GiB = 256 × 1024 = 262144 MiB<br>Number of files = 262144 ÷ 8 = 32768 files</p></section>
  </section>

  <section>
    <section><h4><b>1(e)(ii)</b> State the difference between MiB and MB. [2]</h4></section>
    <section><p>MiB is based on binary (1024 bytes), while MB is based on decimal (1000 bytes). Therefore, 1 MiB = 1024 KB, but 1 MB = 1000 KB.</p></section>
  </section>

  <section>
    <section><h4><b>1(f)</b> Which is primary storage? [1]</h4></section>
    <section><p><b>Answer:</b> RAM</p></section>
  </section>

  <section>
    <section><h4><b>1(g)</b> Explain one benefit of data compression when transmitting data. [2]</h4></section>
    <section><p>Data compression reduces file size, allowing data to be transmitted faster. It also uses less bandwidth, making communication more efficient.</p></section>
  </section>

  <section>
    <section><h4><b>1(h)</b> Explain one advantage of multi-core processors when processing spacecraft data in real time. [2]</h4></section>
    <section><p>Multi-core processors can process multiple tasks simultaneously, improving performance. This allows real-time data processing without delays.</p></section>
  </section>

  <section>
    <section><h4><b>1(i)</b> The diagram shows a CPU. Label two parts of a CPU diagram. [2]</h4></section>
    <section><p>ALU, Cache</p></section>
  </section>

  <section>
    <section><h4><b>1(j)</b> Describe how a CPU processes instructions. [4]</h4></section>
    <section><p>The CPU fetches the instruction from memory.<br>It then decodes the instruction to understand what action is required.<br>The instruction is executed by the ALU or other components.<br>The result is stored in registers or memory.</p></section>
  </section>

</section>
<meta name='description' content='MOCK – 6'>
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
					<h4>MOCK</h4>
					<h2>6</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
 <!-- ================= QUESTION 1 ================= -->
<section>
  <section><h3>Question 1</h3></section>

  <section>
    <section><h4><b>1(a)</b> Which one of the following is primarily responsible for executing program instructions? [1]</h4></section>
    <section><p><b>Answer:</b> CPU</p></section>
  </section>

  <section>
    <section><h4><b>1(b)</b> Which type of memory is non-volatile? [1]</h4></section>
    <section><p><b>Answer:</b> ROM</p></section>
  </section>

  <section>
    <section><h4><b>1(c)</b> Explain why solid-state storage is important for use in spacecraft during space missions. [2]</h4></section>
    <section><p>Solid-state storage has no moving parts, making it more durable and resistant to vibration. It is faster and more reliable, reducing risk of data loss.</p></section>
  </section>

  <section>
    <section><h4><b>1(d)(i)</b> Explain one advantage of using embedded systems in spacecraft. [2]</h4></section>
    <section><p>They are dedicated to a specific task, making them efficient and reliable for real-time processing.</p></section>
  </section>

  <section>
    <section><h4><b>1(d)(ii)</b> Explain one disadvantage of embedded systems. [2]</h4></section>
    <section><p>They are difficult to upgrade or modify and may need full replacement if they fail.</p></section>
  </section>

  <section>
    <section><h4><b>1(e)(i)</b> Calculate number of files (256 GiB, 8 MiB each). [2]</h4></section>
    <section><p>256 × 1024 = 262144 MiB<br>262144 ÷ 8 = 32768 files</p></section>
  </section>

  <section>
    <section><h4><b>1(e)(ii)</b> State the difference between MiB and MB. [2]</h4></section>
    <section><p>MiB uses binary (1024), MB uses decimal (1000).</p></section>
  </section>

  <section>
    <section><h4><b>1(f)</b> Which is primary storage? [1]</h4></section>
    <section><p><b>Answer:</b> RAM</p></section>
  </section>

  <section>
    <section><h4><b>1(g)</b> Explain one benefit of data compression. [2]</h4></section>
    <section><p>Reduces file size, making transmission faster and using less bandwidth.</p></section>
  </section>

  <section>
    <section><h4><b>1(h)</b> Advantage of multi-core processors. [2]</h4></section>
    <section><p>Can process multiple tasks simultaneously, improving performance.</p></section>
  </section>

  <section>
    <section><h4><b>1(i)</b> Label two CPU components. [2]</h4></section>
    <section><p>ALU, Cache</p></section>
  </section>

  <section>
    <section><h4><b>1(j)</b> Describe how CPU processes instructions. [4]</h4></section>
    <section><p>Fetch → Decode → Execute → Store results.</p></section>
  </section>
</section>


<!-- ================= QUESTION 2 ================= -->
<section>
  <section><h3>Question 2</h3></section>

  <section>
    <section><h4><b>2(a)</b> Which device directs data between networks? [1]</h4></section>
    <section><p><b>Answer:</b> Router</p></section>
  </section>

  <section>
    <section><h4><b>2(b)(i)</b> Two methods to identify devices. [2]</h4></section>
    <section><p>IP address, MAC address</p></section>
  </section>

  <section>
    <section><h4><b>2(b)(ii)</b> Why devices need unique identification. [2]</h4></section>
    <section><p>Ensures data reaches correct destination and enables network management.</p></section>
  </section>

  <section>
    <section><h4><b>2(c)</b> Differences between PAN and WAN. [4]</h4></section>
    <section><p>PAN = small range, cheaper; WAN = large range, slower, more expensive.</p></section>
  </section>

  <section>
    <section><h4><b>2(d)</b> Identify two network components. [2]</h4></section>
    <section><p>Server, Router</p></section>
  </section>

  <section>
    <section><h4><b>2(e)</b> Advantage of satellite communication. [2]</h4></section>
    <section><p>Provides global coverage, useful in remote areas.</p></section>
  </section>

  <section>
    <section><h4><b>2(f)</b> TCP/IP layer using IP addresses. [1]</h4></section>
    <section><p><b>Answer:</b> Internet layer</p></section>
  </section>

  <section>
    <section><h4><b>2(g)</b> Cause of delay in satellite communication. [2]</h4></section>
    <section><p>Long distance travel of signals causes latency.</p></section>
  </section>

  <section>
    <section><h4><b>2(h)</b> Describe latency. [2]</h4></section>
    <section><p>Time taken for data to travel from source to destination.</p></section>
  </section>

  <section>
    <section><h4><b>2(i)</b> Benefit of network security. [2]</h4></section>
    <section><p>Prevents unauthorised access and protects data.</p></section>
  </section>

  <section>
    <section><h4><b>2(j)</b> Reason for backups. [2]</h4></section>
    <section><p>Allows data recovery if lost or corrupted.</p></section>
  </section>

  <section>
    <section><h4><b>2(k)</b> Define bandwidth. [2]</h4></section>
    <section><p>Maximum data that can be transmitted per second.</p></section>
  </section>
</section>


<!-- ================= QUESTION 3 ================= -->
<section>
  <section><h3>Question 3</h3></section>

  <section>
    <section><h4><b>3(a)(i)</b> Two online systems and purpose. [2]</h4></section>
    <section><p>Email (communication), Cloud storage (data sharing)</p></section>
  </section>

  <section>
    <section><h4><b>3(a)(ii)</b> Two features of online systems. [2]</h4></section>
    <section><p>Real-time communication, Remote access</p></section>
  </section>

  <section>
    <section><h4><b>3(b)</b> Role of authentication. [1]</h4></section>
    <section><p><b>Answer:</b> Verify identity</p></section>
  </section>

  <section>
    <section><h4><b>3(c)</b> Advantage of multi-factor authentication. [1]</h4></section>
    <section><p><b>Answer:</b> Higher security</p></section>
  </section>

  <section>
    <section><h4><b>3(d)</b> Positive impact of ICT. [2]</h4></section>
    <section><p>Improves communication and data processing efficiency.</p></section>
  </section>

  <section>
    <section><h4><b>3(e)(i)</b> Difference between real-time and asynchronous. [2]</h4></section>
    <section><p>Real-time is instant, asynchronous has delay.</p></section>
  </section>

  <section>
    <section><h4><b>3(e)(ii)</b> Benefit of acceptable use policy. [2]</h4></section>
    <section><p>Ensures responsible use and reduces misuse risks.</p></section>
  </section>

  <section>
    <section><h4><b>3(e)(iii)</b> Moderation tools use. [2]</h4></section>
    <section><p>Filter harmful content and manage communication.</p></section>
  </section>

  <section>
    <section><h4><b>3(f)</b> Two risks of online systems. [4]</h4></section>
    <section><p>Cyber attacks and system failures affecting communication.</p></section>
  </section>
</section>


<!-- ================= QUESTION 4 ================= -->
<section>
  <section><h3>Question 4</h3></section>

  <section>
    <section><h4><b>4(a)</b> Use of sensors. [2]</h4></section>
    <section><p>Monitor temperature, pressure, and oxygen levels.</p></section>
  </section>

  <section>
    <section><h4><b>4(b)</b> Satellite communication. [2]</h4></section>
    <section><p>Data is sent via satellite between spacecraft and Earth.</p></section>
  </section>

  <section>
    <section><h4><b>4(c)</b> Two impacts of ICT. [4]</h4></section>
    <section><p>Improves decision-making and reduces human error.</p></section>
  </section>

  <section>
    <section><h4><b>4(d)</b> Data protection laws importance. [2]</h4></section>
    <section><p>Protect privacy and ensure legal data usage.</p></section>
  </section>

  <section>
    <section><h4><b>4(e)</b> Impact of ICT on employment. [2]</h4></section>
    <section><p>Creates skilled jobs but reduces manual roles.</p></section>
  </section>

  <section>
    <section><h4><b>4(f)</b> Use of telemetry data. [2]</h4></section>
    <section><p>Monitors spacecraft systems and supports decision-making.</p></section>
  </section>

  <section>
    <section><h4><b>4(g)</b> Discuss ethical issues. [8]</h4></section>
    <section><p>Improves safety and efficiency but raises privacy, security, and dependency risks.</p></section>
  </section>
</section>


<!-- ================= QUESTION 5 ================= -->
<section>
  <section><h3>Question 5</h3></section>

  <section>
    <section><h4><b>5(a)</b> Two reasons for application software. [4]</h4></section>
    <section><p>Data processing and data visualisation.</p></section>
  </section>

  <section>
    <section><h4><b>5(b)</b> Two communication software purposes. [2]</h4></section>
    <section><p>Video conferencing, Messaging</p></section>
  </section>

  <section>
    <section><h4><b>5(c)</b> Importance of system software. [2]</h4></section>
    <section><p>Manages hardware and runs applications.</p></section>
  </section>

  <section>
    <section><h4><b>5(d)</b> Two input devices. [2]</h4></section>
    <section><p>Microphone, Touchscreen</p></section>
  </section>

  <section>
    <section><h4><b>5(e)</b> Why SSD does not need defragmentation. [2]</h4></section>
    <section><p>No moving parts; fragmentation does not affect speed.</p></section>
  </section>

  <section>
    <section><h4><b>5(f)</b> Utility software type. [1]</h4></section>
    <section><p><b>Answer:</b> Compression software</p></section>
  </section>

  <section>
    <section><h4><b>5(g)</b> Discuss ICT in research and collaboration. [8]</h4></section>
    <section><p>Improves collaboration, speed, and accuracy but has risks like cyber attacks, failures, and high cost.</p></section>
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

