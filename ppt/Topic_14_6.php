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
  <h3>SECTION F – IT Governance, Security & Business Continuity</h3>
</section>

<section>
  <section>
    <h4><b>64) </b>Define the term IT governance.</h4>
  </section>
  <section>
    <p>IT governance is the framework of policies, procedures, and responsibilities used by an organisation to ensure that IT systems are managed effectively, securely, and in a way that supports business objectives. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>65) </b>Explain why IT governance is important in organizations.</h4>
  </section>

  <section>
    <p>IT governance is important in organisations because it ensures that IT systems are managed effectively, securely, and in alignment with organisational goals. [4]</p>
  </section>

  <section>
    <p>Effective IT governance provides clear policies, roles, and responsibilities for how IT resources are used. This helps organisations make informed decisions about IT investments and ensures technology supports business objectives.</p>
  </section>

  <section>
    <p>IT governance also improves security, risk management, and compliance by enforcing standards and controls to protect data and systems.</p>
  </section>

  <section>
    <p>It helps organisations comply with legal and regulatory requirements, reduces the risk of system failures or data breaches, and promotes accountability and transparency.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>66) </b>Define the term IT security.</h4>
  </section>
  <section>
    <p>IT security is the protection of computer systems, networks, and data from unauthorised access, misuse, damage, or loss, using measures such as access control, authentication, and encryption. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>67) </b>Explain three reasons why IT security is important in organizations.</h4>
  </section>

  <section>
    <p><b>Protection of Sensitive Data</b></p>
  </section>

  <section>
    <p>Organisations store sensitive data such as personal information, financial records, and confidential business data. IT security measures protect this data from unauthorised access and cyberattacks.</p>
  </section>

  <section>
    <p><b>Maintaining Business Continuity</b></p>
  </section>

  <section>
    <p>IT security helps prevent disruptions caused by malware or hacking, reducing downtime and ensuring business operations continue smoothly.</p>
  </section>

  <section>
    <p><b>Legal Compliance and Reputation Protection</b></p>
  </section>

  <section>
    <p>Strong IT security helps organisations meet legal requirements, avoid fines, and protect their reputation by preventing data breaches. [6]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>68) </b>Explain how access control helps protect organizational data.</h4>
  </section>

  <section>
    <p>Access control helps protect organisational data by restricting who can access data and what actions they are allowed to perform. [4]</p>
  </section>

  <section>
    <p>By assigning permissions based on user roles, access control ensures only authorised users can view, modify, or delete specific data.</p>
  </section>

  <section>
    <p>This reduces the risk of unauthorised access, data leaks, and accidental data loss.</p>
  </section>

  <section>
    <p>Access control also supports accountability by allowing user actions to be monitored and logged.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>69) </b>Explain the purpose of encryption in IT security.</h4>
  </section>

  <section>
    <p>The purpose of encryption in IT security is to protect data from unauthorised access by converting it into an unreadable format. [4]</p>
  </section>

  <section>
    <p>Encryption converts readable data into coded data using an algorithm and encryption key, ensuring only authorised users can access it.</p>
  </section>

  <section>
    <p>This protects data even if it is intercepted, stolen, or accessed without permission.</p>
  </section>

  <section>
    <p>Encryption maintains confidentiality and integrity, especially for data stored on devices or transmitted over networks.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>70) </b>Explain the purpose of authentication in securing IT systems.</h4>
  </section>

  <section>
    <p>The purpose of authentication is to verify the identity of users before granting access to systems and data. [4]</p>
  </section>

  <section>
    <p>Authentication uses credentials such as passwords, PINs, biometrics, or security tokens to confirm identity.</p>
  </section>

  <section>
    <p>This prevents unauthorised access, data breaches, and misuse of IT systems.</p>
  </section>

  <section>
    <p>Authentication also supports accountability by ensuring actions can be traced to legitimate users.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>71) </b>Define the term business continuity.</h4>
  </section>
  <section>
    <p>Business continuity is the ability of an organisation to continue its critical operations and services during and after unexpected disruptions, such as system failures, cyberattacks, or natural disasters. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>72) </b>Explain why business continuity planning is important.</h4>
  </section>

  <section>
    <p>Business continuity planning is important because it ensures that critical operations can continue during disruptions. [4]</p>
  </section>

  <section>
    <p>It reduces downtime caused by system failures, cyberattacks, or disasters and helps maintain essential services.</p>
  </section>

  <section>
    <p>Planning protects reputation and customer trust by minimising service interruption.</p>
  </section>

  <section>
    <p>It also supports legal compliance and improves organisational resilience.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>73) </b>Define the term disaster recovery.</h4>
  </section>
  <section>
    <p>Disaster recovery is the process of restoring an organisation’s IT systems, data, and infrastructure after a disruptive event in order to resume normal operations as quickly as possible. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>74) </b>Explain the difference between business continuity and disaster recovery.</h4>
  </section>

  <section>
    <p>The difference between business continuity and disaster recovery is based on focus and timing. [4]</p>
  </section>

  <section>
    <p>Business continuity focuses on keeping critical operations running during a disruption.</p>
  </section>

  <section>
    <p>Disaster recovery focuses on restoring IT systems and data after a disruption has occurred.</p>
  </section>

  <section>
    <p>In summary, business continuity keeps the business running, while disaster recovery restores systems.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>75) </b>Explain two methods used in disaster recovery.</h4>
  </section>

  <section>
    <p><b>Regular Data Backups</b></p>
  </section>

  <section>
    <p>Organisations create copies of important data and store them securely so data can be restored after disasters.</p>
  </section>

  <section>
    <p><b>Redundant Systems (Failover)</b></p>
  </section>

  <section>
    <p>Duplicate systems can take over if the main system fails, reducing downtime and maintaining operations. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>76) </b>Explain the purpose of backup strategies in organizations.</h4>
  </section>

  <section>
    <p>The purpose of backup strategies is to ensure data can be recovered if lost or damaged. [4]</p>
  </section>

  <section>
    <p>Backups protect against data loss caused by hardware failure, cyberattacks, human error, or disasters.</p>
  </section>

  <section>
    <p>Effective backup strategies reduce downtime and support business continuity.</p>
  </section>

  <section>
    <p>They also help organisations meet legal and regulatory requirements.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>77) </b>Explain how cloud computing supports business continuity.</h4>
  </section>

  <section>
    <p>Cloud computing supports business continuity by ensuring systems and data remain accessible during failures. [4]</p>
  </section>

  <section>
    <p>Data stored in remote cloud data centres remains available even if local systems fail.</p>
  </section>

  <section>
    <p>This allows organisations to restore services quickly and reduce the risk of permanent data loss.</p>
  </section>
</section>
`
	 
 

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

