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
    <h4><b>1) </b>State two examples of modern digital devices.</h4>
  </section>
  <section>
    <p>Two examples of modern digital devices are a laptop computer and a mobile (smart) phone. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>2) </b>Define the term device.</h4>
  </section>
  <section>
    <p>A device is an electronic piece of technology that is used to process, store or communicate data. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>3) </b>Define the term feature of a device.</h4>
  </section>
  <section>
    <p>A feature is a distinctive part or characteristic of a device, such as its size, shape, screen type or built-in components. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>4) </b>Define the term function of a device.</h4>
  </section>
  <section>
    <p>A function is the way a device is used or what it is used for, such as making phone calls, browsing the internet or playing media. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>5) </b>Explain the difference between a feature and a function of a device.</h4>
  </section>
  <section>
    <p>A feature is a physical or software characteristic of a device, such as a touchscreen or fingerprint scanner. A function is what the device does or how it is used, such as making calls, sending messages or accessing the internet. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>6) </b>State two hardware features commonly found on a mobile phone.</h4>
  </section>
  <section>
    <p>Two hardware features commonly found on a mobile phone are a touchscreen display and a camera. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>7) </b>State two functions of a mobile phone.</h4>
  </section>
  <section>
    <p>Two functions of a mobile phone are making and receiving voice calls and accessing the internet and social media. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>8) </b>Define the term hardware.</h4>
  </section>
  <section>
    <p>Hardware refers to the physical components of a device that can be seen and touched, such as the keyboard, screen, processor and storage. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>9) </b>Explain why hardware is essential for a device to operate.</h4>
  </section>
  <section>
    <p>Hardware is essential because it provides the physical components required to input, process, store and output data. Without hardware, software cannot run and the device cannot perform any tasks. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>10) </b>State two examples of internal hardware components.</h4>
  </section>
  <section>
    <p>Two examples of internal hardware components are the processor (CPU) and Random Access Memory (RAM). [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>11) </b>State two examples of external hardware components.</h4>
  </section>
  <section>
    <p>Two examples of external hardware components are a keyboard and a mouse. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>12) </b>Define the term portability.</h4>
  </section>
  <section>
    <p>Portability refers to how easy it is to carry a device from one place to another, usually influenced by its size and weight. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>13) </b>Explain why portability is important for mobile devices.</h4>
  </section>
  <section>
    <p>Portability is important for mobile devices because they are designed to be carried and used in different locations. A lightweight and compact device is more convenient for users to transport and use while travelling. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>14) </b>Give one disadvantage of making a device very small to improve portability.</h4>
  </section>
  <section>
    <p>Making a device very small can reduce performance or battery life, or make the device harder to use due to smaller components. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>15) </b>State one type of device where portability is not important.</h4>
  </section>
  <section>
    <p>A desktop computer is a device where portability is not important. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>16) </b>Define the term performance of a device.</h4>
  </section>
  <section>
    <p>Performance refers to how well a device carries out its intended purpose, such as how quickly and efficiently it processes tasks. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>17) </b>Explain why poor performance can frustrate users.</h4>
  </section>
  <section>
    <p>Poor performance can cause applications to open slowly or respond late, which wastes time. This makes it difficult for users to complete tasks efficiently, leading to frustration. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>18) </b>State one example of a task that requires high device performance.</h4>
  </section>
  <section>
    <p>Video editing requires a high level of device performance. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>19) </b>Explain why a desktop PC in a train station needs high performance.</h4>
  </section>
  <section>
    <p>The desktop PC needs high performance because it must process large amounts of real-time data quickly, such as train schedules. High performance ensures accurate and timely updates are displayed to staff and passengers. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>20) </b>Explain why a speaker in a train station does not require high performance.</h4>
  </section>
  <section>
    <p>A speaker only needs to output audio signals, which requires minimal processing. It does not perform complex tasks, so high performance hardware is unnecessary. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>21) </b>Define the term storage.</h4>
  </section>
  <section>
    <p>Storage is hardware used to store data and programs permanently or for long periods. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>22) </b>State two examples of storage devices.</h4>
  </section>
  <section>
    <p>Two examples of storage devices are Hard Disk Drives (HDD) and Solid-State Drives (SSD). [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>23) </b>Explain why tablets use solid-state storage instead of magnetic storage.</h4>
  </section>
  <section>
    <p>Solid-state storage has no moving parts, making it less likely to be damaged if dropped. It is also smaller and faster, improving portability and performance. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>24) </b>State one advantage of solid-state storage over magnetic storage.</h4>
  </section>
  <section>
    <p>Solid-state storage is faster at reading and writing data. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>25) </b>State one disadvantage of solid-state storage compared to HDDs.</h4>
  </section>
  <section>
    <p>Solid-state storage is more expensive at higher capacities than HDDs. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>26) </b>Explain why some desktop computers use both an SSD and an HDD.</h4>
  </section>
  <section>
    <p>An SSD is used to store the operating system and applications to improve speed. An HDD is used to store large amounts of data at a lower cost. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>27) </b>Define the term RAID storage.</h4>
  </section>
  <section>
    <p>RAID storage is a system where multiple storage devices are combined to act as a single storage unit. It is used to improve performance, reliability, or both. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>28) </b>Explain how RAID 0 improves performance.</h4>
  </section>
  <section>
    <p>RAID 0 splits data across multiple disks so data can be read and written simultaneously. This increases data transfer speed and overall system performance. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>29) </b>Explain how RAID 1 protects data.</h4>
  </section>
  <section>
    <p>RAID 1 creates exact copies (mirrors) of data across multiple disks. If one disk fails, the data is still available from the other disk, preventing data loss. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>30) </b>Define the term user interface.</h4>
  </section>
  <section>
    <p>A user interface is the part of a device that allows a user to interact with it, such as buttons or a graphical screen. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>31) </b>State one example of a simple user interface.</h4>
  </section>
  <section>
    <p>A mouse with two buttons is an example of a simple user interface. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>32) </b>Explain why user interfaces should be simple.</h4>
  </section>
  <section>
    <p>A simple interface makes the device easy to learn and use. This reduces user errors and increases efficiency. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>33) </b>Define the term connectivity.</h4>
  </section>
  <section>
    <p>Connectivity is the ability of a device to connect to other devices or networks. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>34) </b>State the two main types of connectivity.</h4>
  </section>
  <section>
    <p>The two main types of connectivity are wired connections and wireless connections. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>35) </b>Give two examples of wired connection cables.</h4>
  </section>
  <section>
    <p>Two examples of wired connection cables are a USB cable and an HDMI cable. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>36) </b>Define the term wired connection.</h4>
  </section>
  <section>
    <p>A wired connection is a method of connecting devices using physical cables to transmit data. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>37) </b>Define the term wireless connection.</h4>
  </section>
  <section>
    <p>A wireless connection is a method of connecting devices using radio waves, without physical cables. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>38) </b>State two examples of wireless connectivity technologies.</h4>
  </section>
  <section>
    <p>Two examples of wireless connectivity technologies are Wi-Fi and Bluetooth. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>39) </b>Define the term port.</h4>
  </section>
  <section>
    <p>A port is a physical connection point on a device that allows a cable to be plugged in. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>40) </b>Define the term wireless network interface card (WNIC).</h4>
  </section>
  <section>
    <p>A wireless network interface card is hardware that allows a device to connect to a wireless network. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>41) </b>Explain why wireless connectivity is important for portable devices.</h4>
  </section>
  <section>
    <p>Wireless connectivity allows portable devices to connect to networks without cables, increasing mobility. This ensures the device remains portable and convenient to use in different locations. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>42) </b>State one disadvantage of adding many ports to a portable device.</h4>
  </section>
  <section>
    <p>Adding many ports can increase the size of the device, reducing portability. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>43) </b>Explain why HDMI cables provide better display quality than VGA cables.</h4>
  </section>
  <section>
    <p>HDMI cables have higher data transmission speeds than VGA cables. This allows higher-resolution audio and video data to be transmitted with better quality. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>44) </b>State two versions of USB cables.</h4>
  </section>
  <section>
    <p>Two versions of USB cables are USB 2.0 and USB 3.0. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>45) </b>Explain why USB 3.0 improves device performance compared to USB 2.0.</h4>
  </section>
  <section>
    <p>USB 3.0 has higher data transfer speeds, allowing data to be moved more quickly. This reduces waiting time when transferring large files, improving performance. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>46) </b>Define the term USB-C.</h4>
  </section>
  <section>
    <p>USB-C is a modern, universal USB connection standard that supports fast data transfer and power delivery. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>47) </b>Explain one benefit of USB-C for users.</h4>
  </section>
  <section>
    <p>USB-C allows one cable type to be used across multiple devices, improving convenience. It also supports fast charging and high data transfer speeds. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>48) </b>Define the term media support.</h4>
  </section>
  <section>
    <p>Media support refers to the types of storage media a device can use, such as memory cards or USB drives. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>49) </b>State two examples of storage media.</h4>
  </section>
  <section>
    <p>Two examples of storage media are SD cards and optical discs (CD/DVD). [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>50) </b>Explain why many modern laptops no longer include optical disk drives.</h4>
  </section>
  <section>
    <p>Optical drives increase manufacturing costs and device size. They are also less commonly used due to cloud storage and digital downloads. [2]</p>
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

