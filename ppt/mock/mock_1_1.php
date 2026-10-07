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

	<meta name='description' content='Mock 1'>
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
					<h4>Mock 1</h4>
					<h2></h2>
					<?= ppt_teacher_credit_markup() ?>
 </section> 

    <!-- 1(a) -->
    <section>
        <section>
            <h4><b>1(a)</b> State one specialized input device (other than a basic microphone) that could be used to capture Yash’s voice, and give one reason why it is suitable. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A USB condenser microphone could be used to capture Yash’s voice.</p>
            <p>It is suitable because it records high-quality digital audio clearly with reduced background noise, making it ideal for professional live radio streaming.</p>
        </section>
    </section>

    <!-- 1(b)(i) -->
    <section>
        <section>
            <h4><b>1(b)(i)</b> Which connectivity method is most suitable? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> USB (wired)</p>
        </section>
    </section>

    <!-- 1(b)(ii) -->
    <section>
        <section>
            <h4><b>1(b)(ii)</b> Explain why your chosen answer is more suitable than one other option. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>USB wired connections are more reliable and provide faster, stable data transfer with very low latency during live streaming.</p>
            <p>Unlike Bluetooth, USB connections are less affected by interference and signal loss, improving audio quality.</p>
        </section>
    </section>

    <!-- 1(b)(iii) -->
    <section>
        <section>
            <h4><b>1(b)(iii)</b> Which best describes the impact of high latency on a live stream? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Audio becomes delayed and out of sync with real-time events</p>
        </section>
    </section>

    <!-- 1(c) -->
    <section>
        <section>
            <h4><b>1(c)</b> Describe in detail how streaming works when Yash broadcasts her radio show. (3)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Yash’s audio is first captured using an input device such as a microphone and converted into digital data by the computer.</p>
            <p>The audio data is compressed and transmitted continuously over the Internet to a streaming server.</p>
            <p>Listeners receive the data packets through streaming software or a web browser, allowing them to listen to the show in real time without downloading the entire audio file first.</p>
        </section>
    </section>

    <!-- 1(d) -->
    <section>
        <section>
            <h4><b>1(d)</b> State the type of network used and explain why it is required instead of a LAN. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A WAN (Wide Area Network) is required.</p>
            <p>This is because Yash’s radio show is streamed globally over the Internet to listeners in different countries, whereas a LAN only covers a small local area such as a building or office.</p>
        </section>
    </section>

    <!-- 1(e) -->
    <section>
        <section>
            <h4><b>1(e)</b> Explain one way copyright legislation affects how a listener can use a recording of Yash’s live radio stream. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Copyright legislation prevents listeners from copying, sharing, or redistributing recordings of Yash’s radio stream without permission.</p>
            <p>This protects Yash’s ownership rights and helps prevent illegal distribution of her content.</p>
        </section>
    </section>

    <!-- 1(f)(i) -->
    <section>
        <section>
            <h4><b>1(f)(i)</b> Construct an expression to show how many recordings can be stored in RAM at once. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>16 GiB = 16 × 1024 = 16384 MiB</p>
            <p>16384 ÷ 512 = 32 recordings</p>
            <p>Final Answer: 32 recordings</p>
        </section>
        <section>

            <p><b>Explanation:</b> Convert storage into the same unit before dividing.</p>
        </section>
    </section>

    <!-- 1(f)(ii) -->
    <section>
        <section>
            <h4><b>1(f)(ii)</b> Which is a benefit of increasing RAM? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> More recordings can be processed simultaneously without slowing down</p>
        </section>
    </section>

    <!-- 1(f)(iii) -->
    <section>
        <section>
            <h4><b>1(f)(iii)</b> State two benefits of using secondary storage rather than RAM to save recordings. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Secondary storage is non-volatile, so recordings are not lost when the power is turned off.</p>
            <p>Secondary storage devices usually provide much larger storage capacity for saving large audio collections.</p>
        </section>
    </section>

    <!-- 1(f)(iv) -->
    <section>
        <section>
            <h4><b>1(f)(iv)</b> Which does the ‘A’ stand for in RAM? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Access</p>
        </section>
    </section>

    <!-- 1(f)(v) -->
    <section>
        <section>
            <h4><b>1(f)(v)</b> State the reason why RAM is described as ‘random access’. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>RAM is called random access because data can be read from or written to any memory location directly without accessing data in sequence first.</p>
            <p>This allows very fast access to stored instructions and data.</p>
        </section>
    </section>

    <!-- 1(g)(i) -->
    <section>
        <section>
            <h4><b>1(g)(i)</b> State how the speed of a processor is measured. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Gigahertz (GHz)</p>
        </section>
    </section>

    <!-- 1(g)(ii) -->
    <section>
        <section>
            <h4><b>1(g)(ii)</b> State one feature of a laptop that allows expansion or improvement of audio processing performance. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Additional RAM slots</p>
        </section>
    </section>

    <!-- 1(h)(i) -->
    <section>
        <section>
            <h4><b>1(h)(i)</b> State one risk to a listener’s personal information when subscribing online. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Personal data could be stolen by hackers through data breaches or phishing attacks</p>
        </section>
    </section>

    <!-- 1(h)(ii) -->
    <section>
        <section>
            <h4><b>1(h)(ii)</b> Describe one way a listener can protect personal information when subscribing. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Listeners should use strong passwords and enable multi-factor authentication when creating online accounts.</p>
            <p>This reduces the risk of unauthorized access to their personal information.</p>
        </section>
    </section>

    <!-- 1(h)(iii) -->
    <section>
        <section>
            <h4><b>1(h)(iii)</b> Explain two legal requirements Yash must follow when storing listeners’ personal information. (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p><b>Requirement 1:</b></p>
            <p>Yash must store personal data securely using methods such as encryption and password protection.</p>
            <p>This helps prevent unauthorized access to listeners’ information.</p>

            <p><b>Requirement 2:</b></p>
            <p>Yash must only collect and use personal information for legitimate purposes and with the user’s consent.</p>
            <p>Listeners should also be informed about how their data will be used.</p>
        </section>
    </section>

    <!-- 1(h)(iv) -->
    <section>
        <section>
            <h4><b>1(h)(iv)</b> Describe how an online service can provide the most relevant content to its users. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Online services analyze user activity, preferences, and listening history using algorithms.</p>
            <p>The system then recommends content that matches the user’s interests and previous behavior.</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/mock_1_2.php">next</a></p>
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

