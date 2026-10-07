<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(dirname(__DIR__))), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'>
<head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='Mock 6'>
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
					<h4>Mock 6</h4>
					<h2></h2>
					<?= ppt_teacher_credit_markup() ?>
 </section> 

    <!-- 4(a) -->
    <section>
        <section>
            <h4><b>4(a)</b> Describe one use of sensors in spacecraft systems. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Sensors are used to monitor environmental conditions inside the spacecraft, such as temperature, pressure, and oxygen levels, to help maintain astronaut safety.</p>
        </section>
    </section>

    <!-- 4(b) -->
    <section>
        <section>
            <h4><b>4(b)</b> Explain how satellite communication is used to transmit data. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>The spacecraft transmits signals containing telemetry and communication data to satellites.</p>
            <p>The satellites then relay the signals back to mission control stations on Earth.</p>
        </section>
    </section>

    <!-- 4(c) -->
    <section>
        <section>
            <h4><b>4(c)</b> Explain two impacts of ICT on space missions. (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p><b>Impact 1:</b></p>
            <p>ICT systems allow real-time communication and telemetry monitoring between spacecraft and mission control.</p>
            <p>This improves safety and allows faster decision-making during emergencies.</p>

            <p><b>Impact 2:</b></p>
            <p>ICT enables automation and accurate data processing using sensors and computer systems.</p>
            <p>This reduces human error and improves the efficiency of scientific research.</p>
        </section>
    </section>

    <!-- 4(d) -->
    <section>
        <section>
            <h4><b>4(d)</b> Explain importance of data protection laws for the space agency. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Data protection laws ensure that personal and sensitive information, such as astronaut health records, is stored securely and only accessed by authorized personnel.</p>
            <p>This helps protect privacy and reduces the risk of data misuse.</p>
        </section>
    </section>

    <!-- 4(e) -->
    <section>
        <section>
            <h4><b>4(e)</b> Explain one impact of ICT on employment in the space agency. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Automation and ICT systems may reduce the need for some manual jobs because computers can perform tasks automatically.</p>
            <p>However, ICT also creates new jobs requiring advanced technical and computer skills.</p>
        </section>
    </section>

    <!-- 4(f) -->
    <section>
        <section>
            <h4><b>4(f)</b> Explain how telemetry data is used. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Telemetry data collected from spacecraft sensors is transmitted to mission control to monitor spacecraft performance, environmental conditions, and astronaut safety in real time.</p>
        </section>
    </section>

    <!-- 4(g) -->
    <section>
        <section>
            <h4><b>4(g)</b> Discuss the ethical issues of using ICT in space missions, including monitoring astronaut data. (8)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>ICT systems provide many advantages in space missions. Monitoring systems can track astronaut health data such as heart rate, oxygen levels, and body temperature in real time. This improves astronaut safety because mission control can respond quickly if medical problems occur. ICT also improves communication, navigation, automation, and scientific research accuracy.</p>

            <p>However, there are ethical concerns related to privacy and personal data collection. Astronauts may feel uncomfortable knowing that their movements and health conditions are constantly monitored. Continuous monitoring may reduce personal privacy and create stress.</p>

            <p>Another ethical issue is data security. If hackers gain unauthorized access to health records or mission systems, confidential information could be stolen or misused. There are also concerns about who is allowed to access the data and how long the information is stored.</p>

            <p>ICT systems may also lead to over-reliance on automated systems. If systems fail or produce incorrect data, important decisions could be affected.</p>

            <p>Overall, ICT monitoring systems provide major safety and operational benefits in space missions, but organizations must balance these benefits with privacy rights, ethical responsibility, cybersecurity, and proper data protection procedures.</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/mock_6_5.php">next</a></p>
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

