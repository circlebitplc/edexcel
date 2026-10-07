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

    <!-- 3(a)(i) -->
    <section>
        <section>
            <h4><b>3(a)(i)</b> State the main purpose of an ISP. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> To provide users with access to the Internet</p>
        </section>
    </section>

    <!-- 3(a)(ii) -->
    <section>
        <section>
            <h4><b>3(a)(ii)</b> State two additional components required in a network to allow Internet access. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>• Router</p>
            <p>• Modem</p>
        </section>
    </section>

    <!-- 3(a)(iii) -->
    <section>
        <section>
            <h4><b>3(a)(iii)</b> Describe the role of a web browser. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A web browser retrieves web pages and other online content from web servers using URLs and HTTP/HTTPS protocols.</p>
            <p>It then displays the content so users can interact with websites.</p>
        </section>
    </section>

    <!-- 3(a)(iv) -->
    <section>
        <section>
            <h4><b>3(a)(iv)</b> State one use of an IP address in a network. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> An IP address uniquely identifies a device so data can be sent to the correct destination</p>
        </section>
    </section>

    <!-- 3(a)(v) -->
    <section>
        <section>
            <h4><b>3(a)(v)</b> Explain one advantage of using a MAC address instead of an IP address. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A MAC address is permanently assigned to a network device and does not normally change.</p>
            <p>This makes it more reliable for identifying specific hardware devices on a local network.</p>
        </section>
    </section>

    <!-- 3(b)(i) -->
    <section>
        <section>
            <h4><b>3(b)(i)</b> Give two types of wireless connectivity. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>• Wi-Fi</p>
            <p>• Bluetooth</p>
        </section>
    </section>

    <!-- 3(b)(ii) -->
    <section>
        <section>
            <h4><b>3(b)(ii)</b> Explain why a WAN is required instead of a LAN. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A WAN is required because Yash’s radio stream is accessed by listeners across large geographical distances using the Internet.</p>
            <p>A LAN only covers a limited local area and cannot provide global access.</p>
        </section>
    </section>

    <!-- 3(c)(i) -->
    <section>
        <section>
            <h4><b>3(c)(i)</b> Which GPS statement is correct? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> GPS receivers calculate position using signals from multiple satellites</p>
        </section>
    </section>

    <!-- 3(c)(ii) -->
    <section>
        <section>
            <h4><b>3(c)(ii)</b> Which is a valid use of GPS? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> To embed location metadata in uploaded media</p>
        </section>
    </section>

    <!-- 3(d) -->
    <section>
        <section>
            <h4><b>3(d)</b> Describe one role of a server in a client-server network. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>The server stores and manages files, streaming content, and user data centrally.</p>
            <p>It also responds to requests from client devices connected to the network.</p>
        </section>
    </section>

    <!-- 3(e) -->
    <section>
        <section>
            <h4><b>3(e)</b> Explain one advantage of a client-server network instead of peer-to-peer. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A client-server network provides centralized management and security for files and user accounts.</p>
            <p>This makes backups, updates, and access control easier to manage.</p>
        </section>
    </section>

    <!-- 3(f) -->
    <section>
        <section>
            <h4><b>3(f)</b> Explain one benefit of using a VPN. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A VPN encrypts Internet traffic when Yash connects remotely to the radio station’s network.</p>
            <p>This improves security and helps protect sensitive data from hackers.</p>
        </section>
    </section>

    <!-- 3(g) -->
    <section>
        <section>
            <h4><b>3(g)</b> Describe one method Yash could use to keep her laptop connected to the Internet. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Yash could enable mobile hotspot or tethering on her smartphone and connect the laptop using Wi-Fi or USB.</p>
            <p>This would allow the laptop to use the phone’s mobile data connection.</p>
        </section>
    </section>

    <!-- 3(h) -->
    <section>
        <section>
            <h4><b>3(h)</b> Explain the impact of these connection speeds. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>The download speed is fast enough for receiving online content smoothly.</p>
            <p>However, the extremely low upload speed would cause buffering, delays, reduced audio quality, and interruptions during Yash’s live broadcasts.</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/mock_1_4.php">next</a></p>
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

