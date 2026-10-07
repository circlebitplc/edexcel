<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'>
<head>
<!-- Global site tag (gtag.js) - Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=UA-117524735-1"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'UA-117524735-1');
</script>

<script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
<script>
  (adsbygoogle = window.adsbygoogle || []).push({
    google_ad_client: "ca-pub-6345478157963217",
    enable_page_level_ads: true
  });
</script>
	<meta charset='utf-8'>

	<title class='hightlight-blue'>Enidu Batuwanthudawe</title>

	<meta name='description' content='Computer Architecture'>
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
					<h2> processes management</h2>
					<?= ppt_teacher_credit_markup() ?>
				</section>

				<section>
				<section>
					<h1>Definition of process</h1>
				</section>
				<section>
						<p>A process is defined as an entity which represents the basic unit of work to be implemented in the system.</p>	
				</section>
				<section>
					<p>A process is basically a program in execution. The execution of a process must progress in a sequential fashion.</p>
				</section>
				<section>
				<p>A process is an instance of a computer program that is being executed. It contains the <br><font color=#d8ff00><strong>PROGRAM CODE</strong> </font><br>and its <br> <font color=#d8ff00><strong>CURRENT ACTIVITY.<br></strong> </font> Depending on the operating system (OS), a process may be made up of multiple threads of execution that execute instructions</p>
				</section>
				</section>
				<section>
				<section>
				<h1>tasks when a process is created</h1>
				<p>process inside main memory</p>
				</section>
				<section>
				<p>process inside main memory</p>
				<img src="<?= $pptBase ?>/img/process_inside.png" height=450px >
				</section>
				<section>
					
					<h1>Stack</h1>

						<p>The process Stack contains the temporary data such as <br>method<br>function parameters<br>return address <br>local variables.</p>
				</section>
				<section>
				<h1>Heap</h1>
				<p>This is dynamically allocated memory to a process during its run time.</p>
				</section>
				<section>
				<h1>Data</h1>
				<p>This section contains the global and static variables.</p>
				</section>
				<section>
				<h1>Text</h1>
				<p>This includes the current activity represented by the value of Program Counter and the contents of the processor's registers.</p>
				</section>
				<section>
				<h1>Program</h1>
				<p>A computer program is a collection of instructions that performs a specific task when executed by a computer. When we compare a program with a process, we can conclude that a process is a dynamic instance of a computer program.</p>
				</section>
				<section>
				<p>A part of a computer program that performs a well-defined task is known as an <br><br><font color=#d8ff00><strong> Algorithm.</strong> </font><br><br> A collection of computer programs, libraries and related data are referred to as a software.</p>
				</section>
				</section>
				<section>
					<section>
						<h1>Process Life Cycle</h1>				
					</section>
					<section>
						<img src="<?= $pptBase ?>/img/Process_Life_Cycle.gif" height=650px width=100%>
					</section>
					<section>
						<h1>Start</h1>
						<p>his is the initial state when a process is first started/created.</p>
					</section>
					<section>
						<h1>Ready</h1>
						<p>The process is waiting to be assigned to a processor. Ready processes are waiting to have the processor allocated to them by the operating system so that they can run. Process may come into this state after Start state or while running it by but interrupted by the scheduler to assign CPU to some other process.</p>
					</section>
					<section>
						<h1>Running</h1>
						<p>Once the process has been assigned to a processor by the OS scheduler, the process state is set to running and the processor executes its instructions.</p>
					</section>
					<section>
						<h1>Waiting</h1>
						<p>Process moves into the waiting state if it needs to wait for a resource, such as waiting for user input, or waiting for a file to become available.</p>
					</section>
					<section>
						<h1>Terminated or Exit</h1>
						<p>Once the process finishes its execution, or it is terminated by the operating system, it is moved to the terminated state where it waits to be removed from main memory.</p>
					</section>
				
				</section> 
				<section>
					<section>
						<h1>Process Control Block (PCB)</h1>
					</section>
					<section>
						<p>A Process Control Block is a data structure maintained by the Operating System for every process. The PCB is identified by an integer process ID (PID). A PCB keeps all the information needed to keep track of a process</p>
					</section>
					<section>
						<img src="<?= $pptBase ?>/img/pcb_eng.gif" height=450px width="250px" >
					</section>
					<section>
						<h2>Process State</h2>
						<p>The current state of the process i.e., whether it is ready, running, waiting, or whatever.</p>	
					</section>
					
					<section>
						<h2>Process privileges</h2>
						<p>This is required to allow/disallow access to system resources.</p>	
					</section>
					<section>
						<h2>Process ID</h2>
						<p>Unique identification for each of the process in the operating system.</p>	
					</section>
					<section>
						<h2>Pointer</h2>
						<p>A pointer to parent process.</p>	
					</section>
					<section>
						<h2>Program Counter</h2>
						<p>A program counter is a register in a computer processor that contains the address (location) of the instruction being executed at the current time. As each instruction gets fetched,</p>	
					</section>
					<section>
						<h2>CPU registers</h2>
						<p>Various CPU registers where process need to be stored for execution for running state.</p>	
					</section>
					<section>
						<h2>CPU Scheduling Information</h2>
						<p>Process priority and other scheduling information which is required to schedule the process.</p>	
					</section>
					<section>
						<h2>Memory management information</h2>
						<p>This includes the information of page table, memory limits, Segment table depending on memory used by the operating system.</p>	
					</section>
					<section>
						<h2>Accounting information</h2>
						<p>This includes the amount of CPU used for process execution, time limits, execution ID etc.</p>	
					</section>
					<section>
						<h2>IO status information</h2>
						<p>This includes a list of I/O devices allocated to the process.

</p>	
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

