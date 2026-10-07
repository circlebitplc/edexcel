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

	<meta name='description' content='Topic 3 – THE ONLINE ENVIRONMENT'>
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
					<h4>Topic 3</h4>
					<h2>THE ONLINE ENVIRONMENT</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
<section>
  <h2>SECTION 1 – INTERNET & WORLD WIDE WEB</h2>
</section>

 <section>
  <section><h4><b>1) </b>Define the term internet. [1]</h4></section>
  <section><p>The internet is a global network of interconnected computer systems and devices that communicate using standard protocols.</p></section>
</section>

<section>
  <section><h4><b>2) </b>Define the term world wide web (WWW). [1]</h4></section>
  <section><p>The world wide web is a collection of web pages and websites accessed via the internet.</p></section>
</section>

<section>
  <section><h4><b>3) </b>Explain the difference between the internet and the world wide web. [2]</h4></section>
  <section>
    <table border="1" cellpadding="5">
      <tr>
        <th>Internet</th>
        <th>World Wide Web</th>
      </tr>
      <tr>
        <td>Physical infrastructure (networks, cables, servers)</td>
        <td>Collection of web pages and websites</td>
      </tr>
      <tr>
        <td>Connects computers globally</td>
        <td>Uses the internet to access content</td>
      </tr>
    </table>
  </section>
</section>

<section>
  <section><h4><b>4) </b>Describe the role of a web browser. [2]</h4></section>
  <section>
    <p>A web browser is software that:</p>
    <p>• Requests web pages from web servers using a URL</p>
    <p>• Displays and interprets web content (e.g. HTML, CSS, JavaScript) for the user</p>
  </section>
</section>

<section>
  <section><h4><b>5) </b>Describe the role of a web server. [2]</h4></section>
  <section>
    <p>A web server is hardware/software that:</p>
    <p>• Stores web pages and website data</p>
    <p>• Responds to requests from web browsers by sending the requested web page</p>
  </section>
</section>

<section>
  <section><h4><b>6) </b>Define the term URL (Uniform Resource Locator). [1]</h4></section>
  <section><p>A URL is a text-based address used to locate and access a specific web page on the internet.</p></section>
</section>

<section>
  <section><h4><b>7) </b>Define the term domain name. [1]</h4></section>
  <section><p>A domain name is the human-readable part of a URL that identifies a website, e.g. pearson.com.</p></section>
</section>

<section>
  <section><h4><b>8) </b>Define the term DNS (Domain Name System). [2]</h4></section>
  <section>
    <p>DNS is a system/server that:</p>
    <p>• Stores mappings of domain names to IP addresses</p>
    <p>• Translates domain names into IP addresses so devices can locate web servers</p>
  </section>
</section>

<section>
  <section><h4><b>9) </b>Explain how a URL is converted into an IP address. [4]</h4></section>
  <section>
    <p>• The user enters a URL into the web browser</p>
    <p>• The browser extracts the domain name from the URL</p>
    <p>• A request is sent to a DNS server to find the corresponding IP address</p>
    <p>• The DNS server searches its database (or forwards the request if needed)</p>
    <p>• Once found, the IP address is returned to the browser</p>
  </section>
</section>

<section>
  <section><h4><b>10) </b>Describe how a web page is retrieved from a web server. [4]</h4></section>
  <section>
    <p>• The user enters a URL in the web browser</p>
    <p>• The browser obtains the IP address using DNS</p>
    <p>• The browser sends a request to the web server using the IP address</p>
    <p>• The web server processes the request and sends back the web page data</p>
    <p>• The browser renders and displays the web page to the user</p>
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

