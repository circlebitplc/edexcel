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

	<meta name='description' content='Topic 1'>
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
					<h4>Topic 1</h4>
					<h2> Hardware & Software</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>

	 

<section>
  <section>
    <h4><b>51) </b>Define the term energy consumption.</h4>
  </section>
  <section>
    <p>Energy consumption is the amount of electrical power used by a device. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>52) </b>Explain two reasons why low energy consumption is important to users.</h4>
  </section>
  <section>
    <p>Low energy consumption reduces electricity costs and environmental impact. It also extends battery life, reducing the need for frequent charging. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>53) </b>Define the term expansion capacity.</h4>
  </section>
  <section>
    <p>Expansion capacity is the ability to add or upgrade components in a device, such as extra storage. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>54) </b>State one example of a device with high expansion capacity.</h4>
  </section>
  <section>
    <p>A desktop computer has high expansion capacity. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>55) </b>Explain why expansion capacity is important for some users.</h4>
  </section>
  <section>
    <p>Expansion capacity allows users to increase storage or performance over time. This extends the useful life of the device and reduces replacement costs. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>56) </b>Define the term biometric device.</h4>
  </section>
  <section>
    <p>A biometric device is a security device that uses biological data, such as fingerprints or facial features. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>57) </b>State two examples of biometric data.</h4>
  </section>
  <section>
    <p>Two examples of biometric data are fingerprints and facial features. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>58) </b>Explain why biometric security is more secure than passwords.</h4>
  </section>
  <section>
    <p>Biometric data is unique to each individual, making it difficult to copy. Passwords can be guessed or cracked, whereas biometric data is much harder to fake. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>59) </b>Define the term technological convergence.</h4>
  </section>
  <section>
    <p>Technological convergence is when two or more technologies or devices are combined into a single device, product or service. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>60) </b>Give one example of technological convergence and explain it.</h4>
  </section>
  <section>
    <p>A smartphone is an example of technological convergence. It combines a mobile phone, camera, GPS device, internet browser and media player into one device. [3]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>61) </b>Define the term Internet of Things (IoT).</h4>
  </section>
  <section>
    <p>The Internet of Things (IoT) refers to everyday devices and sensors that are connected to the internet. These devices can collect, send and receive data automatically. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>62) </b>State two examples of devices that are part of the Internet of Things.</h4>
  </section>
  <section>
    <p>Two examples of IoT devices are a smart fridge and a smart washing machine. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>63) </b>Explain one benefit of the Internet of Things to users.</h4>
  </section>
  <section>
    <p>IoT devices can automate everyday tasks, making life more convenient. They can also save time and improve efficiency by working without user intervention. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>64) </b>Define the term embedded system.</h4>
  </section>
  <section>
    <p>An embedded system is a computer system built into another device. It has a dedicated or limited function and uses specialised hardware. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>65) </b>State two characteristics of an embedded system.</h4>
  </section>
  <section>
    <p>Two characteristics of an embedded system are that it has a single or limited purpose and it uses dedicated hardware. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>66) </b>State two examples of embedded systems.</h4>
  </section>
  <section>
    <p>Two examples of embedded systems are a digital alarm clock and a smart watch. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>67) </b>Define the term microprocessor.</h4>
  </section>
  <section>
    <p>A microprocessor is a small processor that processes data and instructions within a device. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>68) </b>Explain the role of a microprocessor in an embedded system.</h4>
  </section>
  <section>
    <p>The microprocessor processes input data and controls the operation of the device. It allows the embedded system to carry out its dedicated function. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>69) </b>Define the term firmware.</h4>
  </section>
  <section>
    <p>Firmware is basic software that provides instructions for a device to function. It is stored permanently in ROM or on a chip. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>70) </b>State two components found in firmware.</h4>
  </section>
  <section>
    <p>Two components found in firmware are the BIOS (Basic Input/Output System) and the bootloader. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>71) </b>Explain the purpose of the BIOS.</h4>
  </section>
  <section>
    <p>The BIOS initialises hardware components when a device is powered on. It prepares the system so the operating system can load correctly. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>72) </b>Explain the role of the bootloader.</h4>
  </section>
  <section>
    <p>The bootloader is responsible for loading the operating system into memory when the device starts. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>73) </b>State two factors used to assess the performance of a device.</h4>
  </section>
  <section>
    <p>Two factors used to assess device performance are processor speed and storage capacity. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>74) </b>Explain how processor speed affects device performance.</h4>
  </section>
  <section>
    <p>A higher processor speed allows more instructions to be processed per second. This results in faster task completion and better performance. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>75) </b>Explain how the number of processor cores affects performance.</h4>
  </section>
  <section>
    <p>Each processor core can process instructions independently. More cores allow multiple tasks to be processed simultaneously, improving performance. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>76) </b>Define the term bandwidth.</h4>
  </section>
  <section>
    <p>Bandwidth is the maximum amount of data that can be transmitted over a network per second. It is a measure of network capacity. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>77) </b>Explain how bandwidth affects the performance of networked devices.</h4>
  </section>
  <section>
    <p>Higher bandwidth allows more data to be transmitted at the same time. This reduces delays and improves the performance of connected devices. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>78) </b>Define the term power efficiency.</h4>
  </section>
  <section>
    <p>Power efficiency refers to how little energy a device uses to perform tasks. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>79) </b>Explain why power efficiency is considered a performance factor.</h4>
  </section>
  <section>
    <p>A power-efficient device needs charging less often, improving usability. It also reduces electricity costs and environmental impact. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>80) </b>Define the term binary.</h4>
  </section>
  <section>
    <p>Binary is a number system that uses only the digits 0 and 1. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>81) </b>Define the term denary.</h4>
  </section>
  <section>
    <p>Denary is a number system that uses the digits 0 to 9. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>82) </b>Explain why computers use binary instead of denary.</h4>
  </section>
  <section>
    <p>Computers use binary because electronic circuits have two states: on and off. Binary data can be processed reliably and accurately by hardware. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>83) </b>Convert the denary number 13 to binary.</h4>
  </section>
  <section>
    <p>13 in denary = 1101 in binary. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>84) </b>Convert the denary number 150 to binary.</h4>
  </section>
  <section>
    <p>150 in denary = 10010110 in binary. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>85) </b>Convert the binary number 01011101 to denary.</h4>
  </section>
  <section>
    <p>01011101 in binary = 93 in denary. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>86) </b>Convert the denary number 7 to binary.</h4>
  </section>
  <section>
    <p>7 in denary = 0111 in binary. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>87) </b>Convert the denary number 10 to binary.</h4>
  </section>
  <section>
    <p>10 in denary = 1010 in binary. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>88) </b>Convert the denary number 51 to binary.</h4>
  </section>
  <section>
    <p>51 in denary = 00110011 in binary. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>89) </b>Convert the denary number 99 to binary.</h4>
  </section>
  <section>
    <p>99 in denary = 01100011 in binary. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>90) </b>Convert the denary number 180 to binary.</h4>
  </section>
  <section>
    <p>180 in denary = 10110100 in binary. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>91) </b>Convert the binary number 0010 to denary.</h4>
  </section>
  <section>
    <p>0010 in binary = 2 in denary. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>92) </b>Convert the binary number 1001 to denary.</h4>
  </section>
  <section>
    <p>1001 in binary = 9 in denary. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>93) </b>Convert the binary number 1111 to denary.</h4>
  </section>
  <section>
    <p>1111 in binary = 15 in denary. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>94) </b>Convert the binary number 00111110 to denary.</h4>
  </section>
  <section>
    <p>00111110 in binary = 62 in denary. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>95) </b>Convert the binary number 10001000 to denary.</h4>
  </section>
  <section>
    <p>10001000 in binary = 136 in denary. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>96) </b>Define the term bit.</h4>
  </section>
  <section>
    <p>A bit is the smallest unit of data, having a value of 0 or 1. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>97) </b>Define the term byte.</h4>
  </section>
  <section>
    <p>A byte is 8 bits and is commonly used to store one character of data. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>98) </b>State how many bytes are in 1 kibibyte (KiB).</h4>
  </section>
  <section>
    <p>1 KiB = 1024 bytes. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>99) </b>Calculate the size of a 10 KiB file in bytes.</h4>
  </section>
  <section>
    <p>10 × 1024 = 10 240 bytes. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>100) </b>Convert 2048 GiB into tebibytes (TiB).</h4>
  </section>
  <section>
    <p>2048 ÷ 1024 = 2 TiB. [1]</p>
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

