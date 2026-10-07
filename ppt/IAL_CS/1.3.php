<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title class="hightlight-blue">Enidu Batuwanthudawe</title>
    <meta name="description" content="IAL Computer Science - Unit 1 Topic 1.3 Software">
    <meta name="author" content="Enidu Batuwanthudawe">

    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <link rel="stylesheet" href="<?= $pptBase ?>/css/reveal.min.css">
    <link rel="stylesheet" href="<?= $pptBase ?>/css/theme/default.css" id="theme">
    <link rel="stylesheet" href="<?= $pptBase ?>/css/custom.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $pptBase ?>/lib/css/zenburn.css">

    <?php if (isset($_GET['print-pdf'])): ?>
    <link rel="stylesheet" href="<?= $pptBase ?>/css/print/pdf.css">
    <?php endif; ?>

    <!--[if lt IE 9]>
    <script src="<?= $pptBase ?>/lib/js/html5shiv.js"></script>
    <![endif]-->

    <script src="<?= $pptBase ?>/js/moment.min.js"></script>
</head>

<body>
<div class="reveal">
<div class="slides">

    <!-- TITLE -->
    <section>
        <h4>Unit 1 – Topic 1.3</h4>
        <h2>Software</h2>
        <?= ppt_teacher_credit_markup() ?>
    </section>

    <section>
        <h3>How to use this tute</h3>
        <p>Read the question first and write your answer in your own notes/tute.</p>
        <p>Use the <b>next slide</b> to reveal the model answer and check your work.</p>
        <p>Focus on the <b>key terms</b> and the wording used in the model answers.</p>
    </section>

    <!-- 1 -->
    <section>
        <section>
            <h4><b>1)</b> What is software?</h4>
        </section>
        <section>
            Software is a collection of programs and instructions that tell a computer system what to do. <b>[2]</b>
        </section>
    </section>

    <!-- 2 -->
    <section>
        <section>
            <h4><b>2)</b> State the difference between hardware and software.</h4>
        </section>
        <section>
            <b>Hardware</b> consists of the physical components of a computer system, whereas <b>software</b> consists of programs and instructions. <b>[2]</b>
        </section>
    </section>

    <!-- 3 -->
    <section>
        <section>
            <h4><b>3)</b> Give three examples of hardware and three examples of software.</h4>
        </section>
        <section>
            <b>Hardware:</b> CPU, RAM and keyboard.<br><b>Software:</b> operating system, web browser and game. <b>[6]</b>
        </section>
    </section>

    <!-- 4 -->
    <section>
        <section>
            <h4><b>4)</b> State the two main categories of software.</h4>
        </section>
        <section>
            The two main categories are <b>system software</b> and <b>application software</b>. <b>[2]</b>
        </section>
    </section>

    <!-- 5 -->
    <section>
        <section>
            <h4><b>5)</b> Define system software.</h4>
        </section>
        <section>
            System software manages and controls computer hardware and provides a platform or environment in which application software can run. <b>[2]</b>
        </section>
    </section>

    <!-- 6 -->
    <section>
        <section>
            <h4><b>6)</b> Why is system software needed?</h4>
        </section>
        <section>
            System software provides a layer between applications and hardware. This means applications do not need to directly control every hardware device themselves. <b>[2]</b>
        </section>
    </section>

    <!-- 7 -->
    <section>
        <section>
            <h4><b>7)</b> Give four examples of system software.</h4>
        </section>
        <section>
            Examples are:<ul><li>Operating systems</li><li>Device drivers</li><li>Utility software</li><li>Language translators</li></ul><b>[4]</b>
        </section>
    </section>

    <!-- 8 -->
    <section>
        <section>
            <h4><b>8)</b> State four characteristics of system software.</h4>
        </section>
        <section>
            System software:<ul><li>works closely with hardware</li><li>provides services for application software</li><li>manages computer resources</li><li>often operates in the background</li></ul><b>[4]</b>
        </section>
    </section>

    <!-- 9 -->
    <section>
        <section>
            <h4><b>9)</b> Define an operating system.</h4>
        </section>
        <section>
            An operating system is system software that manages computer hardware and software resources and provides services to users and applications. <b>[2]</b>
        </section>
    </section>

    <!-- 10 -->
    <section>
        <section>
            <h4><b>10)</b> State five functions of an operating system given in the notes.</h4>
        </section>
        <section>
            The five functions are:<ul><li>memory management</li><li>process management</li><li>peripheral management</li><li>user management</li><li>providing a user interface</li></ul><b>[5]</b>
        </section>
    </section>

    <!-- 11 -->
    <section>
        <section>
            <h4><b>11)</b> What is a device driver?</h4>
        </section>
        <section>
            A device driver is software that enables the operating system to communicate with and control a particular hardware device. <b>[2]</b>
        </section>
    </section>

    <!-- 12 -->
    <section>
        <section>
            <h4><b>12)</b> Explain the role of a device driver.</h4>
        </section>
        <section>
            A device driver provides the interface between the operating system and a specific hardware device, allowing the OS to communicate with and control that device. <b>[3]</b>
        </section>
    </section>

    <!-- 13 -->
    <section>
        <section>
            <h4><b>13)</b> Give four examples of device drivers.</h4>
        </section>
        <section>
            Examples include:<ul><li>printer drivers</li><li>graphics drivers</li><li>keyboard drivers</li><li>network adapter drivers</li></ul><b>[4]</b>
        </section>
    </section>

    <!-- 14 -->
    <section>
        <section>
            <h4><b>14)</b> Complete the communication chain from an application to a hardware device.</h4>
        </section>
        <section>
            <b>Application → Operating System → Device Driver → Hardware Device</b>. <b>[3]</b>
        </section>
    </section>

    <!-- 15 -->
    <section>
        <section>
            <h4><b>15)</b> Define utility software.</h4>
        </section>
        <section>
            Utility software performs maintenance, management, security or optimisation tasks on a computer system. <b>[2]</b>
        </section>
    </section>

    <!-- 16 -->
    <section>
        <section>
            <h4><b>16)</b> Give four examples of utility software.</h4>
        </section>
        <section>
            Examples include:<ul><li>antivirus software</li><li>backup software</li><li>file compression software</li><li>disk analysis or repair software</li></ul><b>[4]</b>
        </section>
    </section>

    <!-- 17 -->
    <section>
        <section>
            <h4><b>17)</b> Define application software.</h4>
        </section>
        <section>
            Application software is designed to help users perform specific tasks. <b>[2]</b>
        </section>
    </section>

    <!-- 18 -->
    <section>
        <section>
            <h4><b>18)</b> Give five tasks that application software can help users perform.</h4>
        </section>
        <section>
            Application software can be used for:<ul><li>writing documents</li><li>browsing the internet</li><li>editing photographs</li><li>playing games</li><li>managing databases</li></ul><b>[5]</b>
        </section>
    </section>

    <!-- 19 -->
    <section>
        <section>
            <h4><b>19)</b> State the purpose of a word processor, spreadsheet, web browser and database application.</h4>
        </section>
        <section>
            <b>Word processor:</b> creating and editing documents.<br><b>Spreadsheet:</b> calculations and data analysis.<br><b>Web browser:</b> accessing web content.<br><b>Database application:</b> storing and managing data. <b>[4]</b>
        </section>
    </section>

    <!-- 20 -->
    <section>
        <section>
            <h4><b>20)</b> What is general-purpose application software?</h4>
        </section>
        <section>
            General-purpose software is designed for many users and common tasks. Examples include word processors, spreadsheets, presentation software and database software. <b>[2]</b>
        </section>
    </section>

    <!-- 21 -->
    <section>
        <section>
            <h4><b>21)</b> What is special-purpose application software?</h4>
        </section>
        <section>
            Special-purpose software is designed to perform a particular task or solve a specific problem. Examples include airline booking systems, school management systems, hospital systems and banking applications. <b>[2]</b>
        </section>
    </section>

    <!-- 22 -->
    <section>
        <section>
            <h4><b>22)</b> What is bespoke software?</h4>
        </section>
        <section>
            Bespoke software is specifically designed for a particular organisation or user's requirements. <b>[2]</b>
        </section>
    </section>

    <!-- 23 -->
    <section>
        <section>
            <h4><b>23)</b> State two advantages of bespoke software.</h4>
        </section>
        <section>
            <ul><li>It can meet the exact requirements of the organisation or user.</li><li>It can include specific features needed by that organisation or user.</li></ul><b>[2]</b>
        </section>
    </section>

    <!-- 24 -->
    <section>
        <section>
            <h4><b>24)</b> State three disadvantages of bespoke software.</h4>
        </section>
        <section>
            <ul><li>It can be expensive to develop.</li><li>It can be time-consuming to develop.</li><li>It requires testing and maintenance.</li></ul><b>[3]</b>
        </section>
    </section>

    <!-- 25 -->
    <section>
        <section>
            <h4><b>25)</b> What is off-the-shelf software?</h4>
        </section>
        <section>
            Off-the-shelf software is ready-made software designed for a large number of users. <b>[2]</b>
        </section>
    </section>

    <!-- 26 -->
    <section>
        <section>
            <h4><b>26)</b> State three advantages of off-the-shelf software.</h4>
        </section>
        <section>
            <ul><li>It is often cheaper.</li><li>It is immediately available.</li><li>It has already been tested.</li></ul><b>[3]</b>
        </section>
    </section>

    <!-- 27 -->
    <section>
        <section>
            <h4><b>27)</b> State two disadvantages of off-the-shelf software.</h4>
        </section>
        <section>
            <ul><li>It may not meet all requirements.</li><li>It may contain unnecessary features.</li></ul><b>[2]</b>
        </section>
    </section>

    <!-- 28 -->
    <section>
        <section>
            <h4><b>28)</b> Compare bespoke and off-the-shelf software.</h4>
        </section>
        <section>
            <b>Bespoke software</b> is designed specifically for a particular organisation or user's requirements, so it can meet exact needs but may be expensive and time-consuming to develop. <b>Off-the-shelf software</b> is ready-made for many users, so it is often cheaper and immediately available, but it may not meet all requirements and may include unnecessary features. <b>[4]</b>
        </section>
    </section>

    <!-- 29 -->
    <section>
        <section>
            <h4><b>29)</b> State the main difference between system software and application software.</h4>
        </section>
        <section>
            System software manages and supports the computer system, whereas application software allows users to perform specific tasks. <b>[2]</b>
        </section>
    </section>

    <!-- 30 -->
    <section>
        <section>
            <h4><b>30)</b> Give two examples of system software and two examples of application software.</h4>
        </section>
        <section>
            <b>System software:</b> operating system and device driver.<br><b>Application software:</b> word processor and web browser. <b>[4]</b>
        </section>
    </section>

    <!-- 31 -->
    <section>
        <section>
            <h4><b>31)</b> Why are language translators needed?</h4>
        </section>
        <section>
            Humans commonly write programs in high-level languages or assembly language, while a processor executes machine instructions. Translators convert programs into a suitable form for execution by the processor. <b>[3]</b>
        </section>
    </section>

    <!-- 32 -->
    <section>
        <section>
            <h4><b>32)</b> State the three main types of language translator.</h4>
        </section>
        <section>
            The three main types are <b>assembler</b>, <b>compiler</b> and <b>interpreter</b>. <b>[3]</b>
        </section>
    </section>

    <!-- 33 -->
    <section>
        <section>
            <h4><b>33)</b> What does an assembler do?</h4>
        </section>
        <section>
            An assembler translates an assembly-language program into machine code. <b>[2]</b>
        </section>
    </section>

    <!-- 34 -->
    <section>
        <section>
            <h4><b>34)</b> What is assembly language?</h4>
        </section>
        <section>
            Assembly language uses mnemonic instructions such as <b>ADD</b>, <b>MOV</b>, <b>LOAD</b> and <b>STORE</b> and is closely related to processor architecture. <b>[2]</b>
        </section>
    </section>

    <!-- 35 -->
    <section>
        <section>
            <h4><b>35)</b> Explain why an assembler is needed.</h4>
        </section>
        <section>
            An assembler translates assembly-language mnemonics into machine-code instructions that the processor can execute. <b>[3]</b>
        </section>
    </section>

    <!-- 36 -->
    <section>
        <section>
            <h4><b>36)</b> What does a compiler do?</h4>
        </section>
        <section>
            A compiler translates an entire high-level language program into machine code or another executable form before the program is executed. <b>[2]</b>
        </section>
    </section>

    <!-- 37 -->
    <section>
        <section>
            <h4><b>37)</b> State two advantages of using a compiler.</h4>
        </section>
        <section>
            <ul><li>The compiled program can usually execute faster after translation.</li><li>Errors can be reported during compilation.</li></ul><b>[2]</b>
        </section>
    </section>

    <!-- 38 -->
    <section>
        <section>
            <h4><b>38)</b> State two disadvantages of using a compiler.</h4>
        </section>
        <section>
            <ul><li>Compilation can take time.</li><li>Source-code changes normally require recompilation.</li></ul><b>[2]</b>
        </section>
    </section>

    <!-- 39 -->
    <section>
        <section>
            <h4><b>39)</b> What does an interpreter do?</h4>
        </section>
        <section>
            An interpreter translates and executes a high-level language program while it is running, typically progressing through instructions or statements during execution. <b>[2]</b>
        </section>
    </section>

    <!-- 40 -->
    <section>
        <section>
            <h4><b>40)</b> State two advantages of using an interpreter.</h4>
        </section>
        <section>
            <ul><li>It is useful for development and testing.</li><li>The source can be run directly through the interpreter.</li></ul><b>[2]</b>
        </section>
    </section>

    <!-- 41 -->
    <section>
        <section>
            <h4><b>41)</b> State one disadvantage of using an interpreter.</h4>
        </section>
        <section>
            Execution can be slower because translation occurs while the program runs. <b>[1]</b>
        </section>
    </section>

    <!-- 42 -->
    <section>
        <section>
            <h4><b>42)</b> Explain the difference between a compiler and an interpreter.</h4>
        </section>
        <section>
            A <b>compiler</b> translates the program before execution, producing machine code or another executable form. An <b>interpreter</b> translates and executes the program while it is running. <b>[3]</b>
        </section>
    </section>

    <!-- 43 -->
    <section>
        <section>
            <h4><b>43)</b> Compare compiler and interpreter in terms of execution speed.</h4>
        </section>
        <section>
            A compiled program can usually execute faster after compilation because translation has already taken place. An interpreted program can be slower because translation occurs during execution. <b>[2]</b>
        </section>
    </section>

    <!-- 44 -->
    <section>
        <section>
            <h4><b>44)</b> Compare compiler and interpreter in terms of errors.</h4>
        </section>
        <section>
            With a compiler, errors can be reported during compilation. With an interpreter, errors are encountered during interpretation or execution. <b>[2]</b>
        </section>
    </section>

    <!-- 45 -->
    <section>
        <section>
            <h4><b>45)</b> Complete the translation sequence for a compiler.</h4>
        </section>
        <section>
            <b>Source code → Compiler → Object / machine code → Executable program</b>. <b>[3]</b>
        </section>
    </section>

    <!-- 46 -->
    <section>
        <section>
            <h4><b>46)</b> Complete the operation of an interpreter.</h4>
        </section>
        <section>
            <b>Source code → Interpreter → Translate instruction → Execute instruction → Next instruction</b>. <b>[3]</b>
        </section>
    </section>

    <!-- 47 -->
    <section>
        <section>
            <h4><b>47)</b> Compare assembler, compiler and interpreter.</h4>
        </section>
        <section>
            <b>Assembler:</b> takes assembly language and translates it into machine code.<br><b>Compiler:</b> takes a high-level language program and translates it before execution.<br><b>Interpreter:</b> takes a high-level language program and translates and executes it during running. <b>[6]</b>
        </section>
    </section>

    <!-- 48 -->
    <section>
        <section>
            <h4><b>48)</b> What is source code?</h4>
        </section>
        <section>
            Source code is the program written by a programmer in a programming language. <b>[2]</b>
        </section>
    </section>

    <!-- 49 -->
    <section>
        <section>
            <h4><b>49)</b> What is machine code?</h4>
        </section>
        <section>
            Machine code consists of instructions that can be executed by a processor and is commonly represented in binary. <b>[2]</b>
        </section>
    </section>

    <!-- 50 -->
    <section>
        <section>
            <h4><b>50)</b> State the difference between source code and machine code.</h4>
        </section>
        <section>
            Source code is written by a programmer in a programming language, whereas machine code consists of instructions directly executable by the processor. <b>[2]</b>
        </section>
    </section>

    <!-- 51 -->
    <section>
        <section>
            <h4><b>51)</b> What is open-source software?</h4>
        </section>
        <section>
            Open-source software is software whose source code is made available for users or developers to access, use, modify and distribute, subject to the relevant licence. <b>[3]</b>
        </section>
    </section>

    <!-- 52 -->
    <section>
        <section>
            <h4><b>52)</b> State four characteristics of open-source software.</h4>
        </section>
        <section>
            <ul><li>Source code is available.</li><li>Code can be inspected.</li><li>Modification may be permitted by the licence.</li><li>Development may involve a community.</li></ul><b>[4]</b>
        </section>
    </section>

    <!-- 53 -->
    <section>
        <section>
            <h4><b>53)</b> State four advantages of open-source software.</h4>
        </section>
        <section>
            <ul><li>Potentially lower software cost.</li><li>Greater opportunity for customisation.</li><li>Collaborative development.</li><li>Greater transparency.</li></ul><b>[4]</b>
        </section>
    </section>

    <!-- 54 -->
    <section>
        <section>
            <h4><b>54)</b> State four disadvantages of open-source software.</h4>
        </section>
        <section>
            <ul><li>Support quality may vary.</li><li>Technical knowledge may be required.</li><li>Compatibility issues may occur.</li><li>Features and documentation can vary.</li></ul><b>[4]</b>
        </section>
    </section>

    <!-- 55 -->
    <section>
        <section>
            <h4><b>55)</b> What is proprietary software?</h4>
        </section>
        <section>
            Proprietary software is owned and controlled by an individual, company or organisation, and its source code is normally not available to the public. It is also commonly called closed-source software. <b>[3]</b>
        </section>
    </section>

    <!-- 56 -->
    <section>
        <section>
            <h4><b>56)</b> State four characteristics of proprietary software.</h4>
        </section>
        <section>
            <ul><li>Source code is usually not publicly available.</li><li>Users receive permission to use the software under a licence.</li><li>Modification is normally restricted.</li><li>Development is controlled by the owner.</li></ul><b>[4]</b>
        </section>
    </section>

    <!-- 57 -->
    <section>
        <section>
            <h4><b>57)</b> State four advantages of proprietary software.</h4>
        </section>
        <section>
            <ul><li>Potential professional customer support.</li><li>Controlled and coordinated development.</li><li>Centrally managed updates.</li><li>Documentation and polished interfaces may be provided.</li></ul><b>[4]</b>
        </section>
    </section>

    <!-- 58 -->
    <section>
        <section>
            <h4><b>58)</b> State three disadvantages of proprietary software.</h4>
        </section>
        <section>
            <ul><li>It may require purchase, licence or subscription fees.</li><li>Customisation may be limited.</li><li>Users may depend on the supplier for updates and support.</li></ul><b>[3]</b>
        </section>
    </section>

    <!-- 59 -->
    <section>
        <section>
            <h4><b>59)</b> Compare open-source and proprietary software.</h4>
        </section>
        <section>
            Open-source software makes its source code available under the relevant licence and may allow modification and collaborative development. Proprietary software is controlled by an owner, with source code normally unavailable to the public and modification usually restricted. <b>[4]</b>
        </section>
    </section>

    <!-- 60 -->
    <section>
        <section>
            <h4><b>60)</b> Compare open-source and proprietary software in terms of support.</h4>
        </section>
        <section>
            Open-source software often relies on community-based support, although the exact support can vary. Proprietary software may include dedicated company support. <b>[2]</b>
        </section>
    </section>

    <!-- 61 -->
    <section>
        <section>
            <h4><b>61)</b> Compare open-source and proprietary software in terms of customisation.</h4>
        </section>
        <section>
            Open-source software usually provides greater opportunity for customisation because the source code may be inspected and modified under its licence. Proprietary software usually has more limited customisation because modification is restricted. <b>[2]</b>
        </section>
    </section>

    <!-- 62 -->
    <section>
        <section>
            <h4><b>62)</b> Explain why open-source software does not automatically mean free software.</h4>
        </section>
        <section>
            The key distinction is access to the source code and the rights granted by the relevant licence. Open-source software may be available at no cost, but 'open source' describes the source-code access and licence conditions rather than automatically meaning that the software is free. <b>[3]</b>
        </section>
    </section>

    <!-- 63 -->
    <section>
        <section>
            <h4><b>63)</b> Explain why proprietary software is not necessarily inferior to open-source software.</h4>
        </section>
        <section>
            Proprietary software may provide professional customer support, controlled development, centrally managed updates, documentation and polished interfaces. Therefore, proprietary software has characteristics that can be useful to users and organisations. <b>[3]</b>
        </section>
    </section>

    <!-- 64 -->
    <section>
        <section>
            <h4><b>64)</b> Explain the role of system software in supporting application software.</h4>
        </section>
        <section>
            System software manages computer resources and provides services and an environment in which application software can run. It acts as a layer between applications and hardware, so applications do not need to control every hardware device directly. <b>[4]</b>
        </section>
    </section>

    <!-- 65 -->
    <section>
        <section>
            <h4><b>65)</b> A school needs software designed specifically for its own management requirements. What type of software is appropriate and why?</h4>
        </section>
        <section>
            <b>Bespoke software</b> would be appropriate because it is specifically designed for the organisation's requirements and can include features that meet the school's exact needs. <b>[3]</b>
        </section>
    </section>

    <!-- 66 -->
    <section>
        <section>
            <h4><b>66)</b> A user needs a ready-made spreadsheet package that is immediately available. What type of software is this and give one advantage.</h4>
        </section>
        <section>
            This is <b>off-the-shelf software</b>. One advantage is that it is immediately available; it is also often cheaper and already tested. <b>[2]</b>
        </section>
    </section>

    <!-- 67 -->
    <section>
        <section>
            <h4><b>67)</b> Explain why a computer cannot directly execute a high-level language program in the form in which it is written.</h4>
        </section>
        <section>
            A processor executes machine instructions, whereas a high-level language program is written in a form intended to be understood and written by programmers. A translator such as a compiler or interpreter is therefore needed to convert it into a suitable form for execution. <b>[3]</b>
        </section>
    </section>

    <!-- 68 -->
    <section>
        <section>
            <h4><b>68)</b> Exam question: Explain the purpose of translators and distinguish the three main types.</h4>
        </section>
        <section>
            Translators convert programs written in assembly language or high-level languages into a form suitable for execution by a processor. An <b>assembler</b> translates assembly language into machine code. A <b>compiler</b> translates an entire high-level program before execution. An <b>interpreter</b> translates and executes a high-level program while it is running. <b>[6]</b>
        </section>
    </section>

    <!-- 69 -->
    <section>
        <section>
            <h4><b>69)</b> Exam question: Compare bespoke and off-the-shelf software, including advantages and disadvantages.</h4>
        </section>
        <section>
            Bespoke software is designed specifically for an organisation or user's requirements, so it can meet exact requirements and include specific features. However, it can be expensive and time-consuming to develop and requires testing and maintenance. Off-the-shelf software is ready-made for a large number of users, so it is often cheaper, immediately available and already tested. However, it may not meet all requirements and may contain unnecessary features. <b>[6]</b>
        </section>
    </section>

    <!-- 70 -->
    <section>
        <section>
            <h4><b>70)</b> Exam question: Compare open-source and proprietary software, including advantages and disadvantages.</h4>
        </section>
        <section>
            Open-source software makes its source code available under a relevant licence and may allow inspection, modification and collaborative development. Advantages include potentially lower cost, greater customisation, collaboration and transparency. Disadvantages can include variable support, technical knowledge requirements, compatibility issues and varying documentation. Proprietary software is controlled by an owner and its source code is normally unavailable to the public. It may provide professional support, controlled development, managed updates and polished documentation/interfaces, but may require fees, provide less customisation and create dependence on the supplier. <b>[8]</b>
        </section>
    </section>

    <!-- 71 -->
    <section>
        <section>
            <h4><b>71)</b> Exam question: Explain the difference between system software and application software with examples.</h4>
        </section>
        <section>
            System software manages and supports the computer system, works closely with hardware and provides services or an environment for application software. Examples include operating systems, device drivers, utilities and translators. Application software helps users perform specific tasks, such as writing documents, browsing the internet, managing databases or creating spreadsheets. <b>[6]</b>
        </section>
    </section>

    <!-- 72 -->
    <section>
        <section>
            <h4><b>72)</b> Exam question: Describe how a high-level program can be translated and executed using a compiler and using an interpreter.</h4>
        </section>
        <section>
            With a <b>compiler</b>, the source code is translated before execution into machine code or another executable form, after which the translated program can be executed. With an <b>interpreter</b>, the source program is translated and executed while it is running, progressing through instructions or statements during execution. <b>[6]</b>
        </section>
    </section>

    <!-- FINAL EXAM CHECKLIST -->
    <section>
        <h3>1.3 Software – Exam Checklist</h3>
        <ul>
            <li>Definition of software and hardware vs software</li>
            <li>System software and its purpose</li>
            <li>Operating systems and their functions</li>
            <li>Device drivers and the OS–driver–hardware relationship</li>
            <li>Utility software and examples</li>
            <li>Application software and common examples</li>
            <li>General-purpose and special-purpose application software</li>
            <li>Bespoke and off-the-shelf software</li>
            <li>System software vs application software</li>
            <li>Why language translators are required</li>
            <li>Assembler, compiler and interpreter</li>
            <li>Compiler vs interpreter</li>
            <li>Source code and machine code</li>
            <li>Open-source software</li>
            <li>Proprietary software</li>
            <li>Open-source vs proprietary software</li>
        </ul>
    </section>

    <section>
        <h3>Important Exam Traps</h3>
        <ul>
            <li>Do not confuse <b>system software</b> with <b>application software</b>.</li>
            <li>A <b>device driver</b> allows the OS to communicate with a specific hardware device.</li>
            <li>An <b>assembler</b> translates assembly language, not high-level language.</li>
            <li>A <b>compiler</b> translates before execution; an <b>interpreter</b> translates and executes while running.</li>
            <li><b>Open source</b> does not automatically mean free; the licence and source-code rights are the key distinction.</li>
            <li><b>Proprietary</b> software is not automatically inferior; it may provide dedicated support and managed updates.</li>
            <li>Do not confuse <b>bespoke</b> software with <b>off-the-shelf</b> software.</li>
            <li>Remember: a processor executes <b>machine instructions</b>.</li>
        </ul>
    </section>

</div>
</div>

<script src="<?= $pptBase ?>/lib/js/head.min.js"></script>
<script src="<?= $pptBase ?>/js/reveal.min.js"></script>

<script>
Reveal.initialize({
    controls: true,
    progress: true,
    history: true,
    center: true,

    theme: Reveal.getQueryHash().theme,
    transition: Reveal.getQueryHash().transition || 'default',

    dependencies: [
        {
            src: '<?= $pptBase ?>/lib/js/classList.js',
            condition: function() {
                return !document.body.classList;
            }
        },
        {
            src: '<?= $pptBase ?>/plugin/markdown/marked.js',
            condition: function() {
                return !!document.querySelector('[data-markdown]');
            }
        },
        {
            src: '<?= $pptBase ?>/plugin/markdown/markdown.js',
            condition: function() {
                return !!document.querySelector('[data-markdown]');
            }
        },
        {
            src: '<?= $pptBase ?>/plugin/highlight/highlight.js',
            async: true,
            callback: function() {
                hljs.initHighlightingOnLoad();
            }
        },
        {
            src: '<?= $pptBase ?>/plugin/zoom-js/zoom.js',
            async: true,
            condition: function() {
                return !!document.body.classList;
            }
        }
    ]
});
</script>

</body>
</html>
