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

	<meta name='description' content='Topic 12 – Manipulating data'>
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
					<h4>Topic 12</h4>
					<h2> Manipulating data</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
<section>
  <section>
    <h4><b>101) </b>State one difference between IEC units and SI units.</h4>
  </section>
  <section>
    <p>IEC units are based on 1024, while SI units are based on 1000. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>102) </b>Explain why a text file usually has a smaller file size than a video file.</h4>
  </section>
  <section>
    <p>A text file stores simple characters, which require very little data. A video file stores images and sound, which require much more data. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>103) </b>A text file has 3072 characters. Each character uses 1 byte. Calculate the file size in bytes and kibibytes.</h4>
  </section>
  <section>
    <p>3072 characters = 3072 bytes. 3072 ÷ 1024 = 3 KiB. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>104) </b>Explain what an expression is when calculating file transfer time.</h4>
  </section>
  <section>
    <p>An expression shows how a calculation is set up, without calculating the final value. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>105) </b>Construct an expression to calculate the time taken to transfer a 10 MiB file over a 10 Gbps connection.</h4>
  </section>
  <section>
    <p>(10 × 8 × 1024 × 1024) ÷ (10 × 1000 × 1000 × 1000). [3]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>106) </b>Define the term software.</h4>
  </section>
  <section>
    <p>Software is a set of instructions that tells a computer what to do. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>107) </b>State the two main types of software.</h4>
  </section>
  <section>
    <p>The two main types of software are systems software and application software. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>108) </b>Define systems software.</h4>
  </section>
  <section>
    <p>Systems software is software that controls and manages the operation of the computer. It allows hardware and software to work together efficiently. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>109) </b>State two examples of systems software.</h4>
  </section>
  <section>
    <p>Two examples of systems software are the operating system and antivirus software. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>110) </b>Define application software.</h4>
  </section>
  <section>
    <p>Application software is software designed to perform specific tasks for the user, such as creating documents or editing images. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>111) </b>State two examples of application software.</h4>
  </section>
  <section>
    <p>Two examples of application software are word processors and spreadsheets. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>112) </b>Define the term operating system.</h4>
  </section>
  <section>
    <p>An operating system is systems software that manages hardware, software and users. It provides an interface between the user and the computer. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>113) </b>Explain two roles of an operating system in managing devices.</h4>
  </section>
  <section>
    <p>The operating system controls peripheral devices such as printers and scanners. It uses device drivers to allow hardware and the computer to communicate properly. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>114) </b>Define the term driver.</h4>
  </section>
  <section>
    <p>A driver is systems software that allows a hardware device to communicate with the operating system. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>115) </b>Define the term interrupt.</h4>
  </section>
  <section>
    <p>An interrupt is a signal sent to the processor to get its attention when a device or process needs action. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>116) </b>Explain how an operating system manages processes.</h4>
  </section>
  <section>
    <p>The operating system allocates processor time and memory to running programs. It ensures processes do not conflict when accessing memory or resources. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>117) </b>Define the term multitasking.</h4>
  </section>
  <section>
    <p>Multitasking is when a computer runs multiple tasks at the same time by sharing processor time. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>118) </b>Explain one benefit of multitasking to users.</h4>
  </section>
  <section>
    <p>Multitasking allows users to run several applications simultaneously, such as browsing the web while listening to music. This improves efficiency and productivity. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>119) </b>Explain two ways an operating system manages users.</h4>
  </section>
  <section>
    <p>The operating system allows user accounts with usernames and passwords. It sets access permissions to restrict files or actions for different users. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>120) </b>Explain two ways an operating system manages security.</h4>
  </section>
  <section>
    <p>The operating system allows password protection and access control. It supports antivirus, firewall and anti-malware software. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>121) </b>Define the term free software.</h4>
  </section>
  <section>
    <p>Free software allows users to study, modify and share the source code. It does not necessarily mean the software is free of charge. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>122) </b>Define the term open-source software.</h4>
  </section>
  <section>
    <p>Open-source software allows users to view, modify and distribute the source code. It is usually available without payment. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>123) </b>Define the term proprietary software.</h4>
  </section>
  <section>
    <p>Proprietary software is software where the source code is not available to users. Users cannot modify or redistribute the software. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>124) </b>State one advantage of proprietary software.</h4>
  </section>
  <section>
    <p>It is professionally tested and supported by the developer. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>125) </b>Define the term Creative Commons.</h4>
  </section>
  <section>
    <p>Creative Commons is a system of licences that allow creators to control how their work is used. It sets permissions such as attribution, non-commercial use or sharing rules. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>126) </b>State two types of Creative Commons licence conditions.</h4>
  </section>
  <section>
    <p>Two Creative Commons licence conditions are attribution and non-commercial use. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>127) </b>Define the term single-user licence.</h4>
  </section>
  <section>
    <p>A single-user licence allows software to be installed and used on one computer only. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>128) </b>Define the term institutional licence.</h4>
  </section>
  <section>
    <p>An institutional licence allows software to be installed on all computers within an organisation. The cost is usually based on the number of users. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>129) </b>Define the term fixed-term licence.</h4>
  </section>
  <section>
    <p>A fixed-term licence allows software to be used for a specific period of time. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>130) </b>Define the term indefinite (perpetual) licence.</h4>
  </section>
  <section>
    <p>An indefinite licence allows the user to use the software forever after a one-time payment. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>131) </b>Define the term network licence.</h4>
  </section>
  <section>
    <p>A network licence installs software on a central server. Multiple computers on the network can access the software. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>132) </b>State two reasons why software is updated.</h4>
  </section>
  <section>
    <p>Software is updated to fix bugs or errors and to improve security. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>133) </b>Define the term software patch.</h4>
  </section>
  <section>
    <p>A software patch is a small update released to fix errors or security issues. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>134) </b>Define the term software upgrade.</h4>
  </section>
  <section>
    <p>A software upgrade is a new version of software with major new features. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>135) </b>Define the term automatic software update.</h4>
  </section>
  <section>
    <p>An automatic update installs software updates without user intervention. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>136) </b>Explain one problem caused by software updates.</h4>
  </section>
  <section>
    <p>Software updates can cause compatibility issues where older hardware or software no longer works correctly. This may reduce performance or stop programs from running. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>137) </b>Explain why compatibility issues can occur after an operating system update.</h4>
  </section>
  <section>
    <p>New operating systems may be designed for newer hardware. Older applications or drivers may no longer be supported, causing failures. [2]</p>
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

