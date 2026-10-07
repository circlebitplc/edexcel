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
    <meta name="description" content="IAL Computer Science - Unit 1 Topic 1.2 Operating Systems">
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
        <h4>Unit 1 – Topic 1.2</h4>
        <h2>Operating Systems</h2>
        <p><small>IAL Computer Science – Exam-Focused Tutorial</small></p>
        <?= ppt_teacher_credit_markup() ?>
    </section>

    <section>
        <h3>How to use this tute</h3>
        <p>Read each question first and try to answer it yourself.</p>
        <p>Use the <b>next slide</b> to check the model answer.</p>
        <p>Learn the <b>key terminology</b> and make sure your answer contains enough detail for the marks available.</p>
    </section>

    <!-- 1 -->
    <section>
        <section>
            <h4><b>1)</b> Define an Operating System (OS).</h4>
        </section>
        <section>
            An Operating System is system software that manages the hardware and software resources of a computer and provides services and an interface for users and application software. Examples include Windows, macOS, Linux, Android and iOS.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 2 -->
    <section>
        <section>
            <h4><b>2)</b> Explain why an operating system is needed.</h4>
        </section>
        <section>
            An operating system manages hardware resources and provides services/interfaces for users and applications. It hides the complexity of the hardware so application software can use the computer's resources without directly controlling the hardware.
            <p><b>[3]</b></p>
        </section>
    </section>

    <!-- 3 -->
    <section>
        <section>
            <h4><b>3)</b> State five core roles of an operating system.</h4>
        </section>
        <section>
            <ul><li>User interface</li><li>User management</li><li>Peripheral management</li><li>Process management</li><li>Memory management</li></ul>
            <p><b>[5]</b></p>
        </section>
    </section>

    <!-- 4 -->
    <section>
        <section>
            <h4><b>4)</b> What is a user interface?</h4>
        </section>
        <section>
            A user interface is the method through which a user interacts with a computer system.
            <p><b>[1]</b></p>
        </section>
    </section>

    <!-- 5 -->
    <section>
        <section>
            <h4><b>5)</b> Describe a Graphical User Interface (GUI).</h4>
        </section>
        <section>
            A GUI allows a user to interact with the computer using graphical elements such as windows, icons, menus and a pointer, rather than typing commands for every operation.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 6 -->
    <section>
        <section>
            <h4><b>6)</b> State two advantages of a GUI.</h4>
        </section>
        <section>
            <ul><li>It is easy to learn and intuitive.</li><li>It is suitable for inexperienced users.</li></ul>
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 7 -->
    <section>
        <section>
            <h4><b>7)</b> State two disadvantages of a GUI.</h4>
        </section>
        <section>
            <ul><li>It can use more memory and processing resources.</li><li>It may be slower for some repetitive tasks performed by experienced users.</li></ul>
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 8 -->
    <section>
        <section>
            <h4><b>8)</b> Describe a Command Line Interface (CLI).</h4>
        </section>
        <section>
            A CLI allows the user to interact with the operating system by typing commands rather than selecting graphical objects.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 9 -->
    <section>
        <section>
            <h4><b>9)</b> State two advantages of a CLI.</h4>
        </section>
        <section>
            <ul><li>It generally uses fewer system resources.</li><li>It is powerful for experienced users and is suitable for scripting and automation.</li></ul>
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 10 -->
    <section>
        <section>
            <h4><b>10)</b> State two disadvantages of a CLI.</h4>
        </section>
        <section>
            <ul><li>Users must remember the correct commands and syntax.</li><li>It is less intuitive for inexperienced users.</li></ul>
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 11 -->
    <section>
        <section>
            <h4><b>11)</b> Explain how an operating system manages users.</h4>
        </section>
        <section>
            The OS creates and manages user accounts, authenticates users, assigns permissions and controls access to files and other resources.
            <p><b>[3]</b></p>
        </section>
    </section>

    <!-- 12 -->
    <section>
        <section>
            <h4><b>12)</b> Explain how an operating system manages peripherals.</h4>
        </section>
        <section>
            The OS manages communication between software and peripheral devices such as keyboards, mice and printers. Device drivers allow the OS to communicate with specific hardware.
            <p><b>[3]</b></p>
        </section>
    </section>

    <!-- 13 -->
    <section>
        <section>
            <h4><b>13)</b> Explain process management as a role of an operating system.</h4>
        </section>
        <section>
            The OS starts and stops processes, allocates CPU time, switches between processes, manages process states and performs process scheduling.
            <p><b>[3]</b></p>
        </section>
    </section>

    <!-- 14 -->
    <section>
        <section>
            <h4><b>14)</b> Explain memory management as a role of an operating system.</h4>
        </section>
        <section>
            The OS tracks used and free memory, determines the memory requirements of processes, allocates and deallocates memory and controls how memory is used.
            <p><b>[3]</b></p>
        </section>
    </section>

    <!-- 15 -->
    <section>
        <section>
            <h4><b>15)</b> Define multitasking.</h4>
        </section>
        <section>
            Multitasking is the ability of an operating system to manage multiple processes so that they appear to execute simultaneously.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 16 -->
    <section>
        <section>
            <h4><b>16)</b> Does multitasking require more than one CPU core? Explain.</h4>
        </section>
        <section>
            No. A single CPU core can multitask by rapidly switching between processes. The rapid switching makes the processes appear to run at the same time.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 17 -->
    <section>
        <section>
            <h4><b>17)</b> Explain time sharing.</h4>
        </section>
        <section>
            Time sharing divides CPU processing time into small intervals and gives processes turns to use the processor. This allows multiple processes to make progress and appear to execute simultaneously.
            <p><b>[3]</b></p>
        </section>
    </section>

    <!-- 18 -->
    <section>
        <section>
            <h4><b>18)</b> What is a time slice or time quantum?</h4>
        </section>
        <section>
            A time slice, or time quantum, is the amount of CPU time allocated to a process before another process is given an opportunity to run.
            <p><b>[1]</b></p>
        </section>
    </section>

    <!-- 19 -->
    <section>
        <section>
            <h4><b>19)</b> State two benefits of multitasking/time sharing.</h4>
        </section>
        <section>
            <ul><li>CPU utilisation can improve because another process can run while one is waiting for I/O.</li><li>The system is more responsive and can run multiple applications apparently at once.</li></ul>
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 20 -->
    <section>
        <section>
            <h4><b>20)</b> State two disadvantages of multitasking/time sharing.</h4>
        </section>
        <section>
            <ul><li>Context switching creates processing overhead.</li><li>Processes compete for CPU, memory and I/O resources and may have to wait for CPU time.</li></ul>
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 21 -->
    <section>
        <section>
            <h4><b>21)</b> Define context switching.</h4>
        </section>
        <section>
            Context switching occurs when the CPU stops executing one process and switches to another. The OS saves the state of the current process and loads the state of the next process.
            <p><b>[3]</b></p>
        </section>
    </section>

    <!-- 22 -->
    <section>
        <section>
            <h4><b>22)</b> Explain why context switching is needed.</h4>
        </section>
        <section>
            The OS must be able to stop one process and allow another to run. Saving the current process state allows it to be restored later so execution can continue from the correct point.
            <p><b>[3]</b></p>
        </section>
    </section>

    <!-- 23 -->
    <section>
        <section>
            <h4><b>23)</b> State information that may be saved during a context switch.</h4>
        </section>
        <section>
            The saved context can include the program counter, CPU register values, status information and other information required to resume execution.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 24 -->
    <section>
        <section>
            <h4><b>24)</b> Define a process and distinguish it from a program.</h4>
        </section>
        <section>
            A program is a set of stored instructions. A process is an instance of a program that is currently being executed.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 25 -->
    <section>
        <section>
            <h4><b>25)</b> State the four main process states.</h4>
        </section>
        <section>
            <ul><li>Ready</li><li>Running</li><li>Waiting/Blocked</li><li>Terminated</li></ul>
            <p><b>[4]</b></p>
        </section>
    </section>

    <!-- 26 -->
    <section>
        <section>
            <h4><b>26)</b> Describe the Ready state.</h4>
        </section>
        <section>
            A process in the Ready state is prepared to run but is waiting for CPU time.
            <p><b>[1]</b></p>
        </section>
    </section>

    <!-- 27 -->
    <section>
        <section>
            <h4><b>27)</b> Describe the Running state.</h4>
        </section>
        <section>
            A process in the Running state is currently being executed by the CPU.
            <p><b>[1]</b></p>
        </section>
    </section>

    <!-- 28 -->
    <section>
        <section>
            <h4><b>28)</b> Describe the Waiting/Blocked state.</h4>
        </section>
        <section>
            A process in the Waiting or Blocked state cannot continue until an event occurs, often an input/output operation completing.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 29 -->
    <section>
        <section>
            <h4><b>29)</b> Describe the Terminated state.</h4>
        </section>
        <section>
            A process enters the Terminated state when it has completed or has been stopped. Its allocated resources can then be released.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 30 -->
    <section>
        <section>
            <h4><b>30)</b> Define process scheduling.</h4>
        </section>
        <section>
            Process scheduling is the process of deciding which ready process should receive CPU time. The scheduler makes this decision.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 31 -->
    <section>
        <section>
            <h4><b>31)</b> Explain First Come, First Served (FCFS) scheduling.</h4>
        </section>
        <section>
            FCFS executes processes according to their arrival order. A process normally continues until it finishes before the next process is selected.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 32 -->
    <section>
        <section>
            <h4><b>32)</b> State one advantage and one disadvantage of FCFS scheduling.</h4>
        </section>
        <section>
            <b>Advantage:</b> It is simple and easy to implement.<br><b>Disadvantage:</b> A long process can delay many processes that arrive after it.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 33 -->
    <section>
        <section>
            <h4><b>33)</b> Explain Shortest Job First (SJF) scheduling.</h4>
        </section>
        <section>
            SJF selects the process with the shortest expected execution time first. It can reduce average waiting time, but execution times may be difficult to predict and long processes may experience starvation.
            <p><b>[3]</b></p>
        </section>
    </section>

    <!-- 34 -->
    <section>
        <section>
            <h4><b>34)</b> State one advantage and one disadvantage of SJF.</h4>
        </section>
        <section>
            <b>Advantage:</b> It can reduce average waiting time.<br><b>Disadvantage:</b> The execution time may be difficult to predict and long jobs may starve.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 35 -->
    <section>
        <section>
            <h4><b>35)</b> Explain Round Robin scheduling.</h4>
        </section>
        <section>
            Round Robin gives each ready process a fixed time quantum in turn. If a process has not finished when its quantum expires, it is placed back in the ready queue so it can receive another time slice later.
            <p><b>[3]</b></p>
        </section>
    </section>

    <!-- 36 -->
    <section>
        <section>
            <h4><b>36)</b> State one advantage and one disadvantage of Round Robin.</h4>
        </section>
        <section>
            <b>Advantage:</b> It is fair and is suitable for interactive systems.<br><b>Disadvantage:</b> Frequent context switching creates overhead, and performance depends on the size of the time quantum.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 37 -->
    <section>
        <section>
            <h4><b>37)</b> State one difference between FCFS and Round Robin.</h4>
        </section>
        <section>
            FCFS follows arrival order and a process normally completes before the next process runs. Round Robin gives each process a fixed time quantum and unfinished processes return to the ready queue for another turn.
            <p><b>[3]</b></p>
        </section>
    </section>

    <!-- 38 -->
    <section>
        <section>
            <h4><b>38)</b> What is a multi-level queue scheduling system?</h4>
        </section>
        <section>
            Processes are divided into different queues, with each queue potentially having a different priority or scheduling policy. This allows different classes of processes to be treated differently.
            <p><b>[3]</b></p>
        </section>
    </section>

    <!-- 39 -->
    <section>
        <section>
            <h4><b>39)</b> State one advantage and one disadvantage of multi-level queue scheduling.</h4>
        </section>
        <section>
            <b>Advantage:</b> Different process classes can be treated using different priorities or policies.<br><b>Disadvantage:</b> The system is more complex and lower-priority processes may suffer starvation.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 40 -->
    <section>
        <section>
            <h4><b>40)</b> Define starvation in process scheduling.</h4>
        </section>
        <section>
            Starvation occurs when a process waits for an excessively long time because other processes continually receive CPU resources.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 41 -->
    <section>
        <section>
            <h4><b>41)</b> Define memory management.</h4>
        </section>
        <section>
            Memory management is the process by which the operating system controls the allocation and use of main memory by processes.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 42 -->
    <section>
        <section>
            <h4><b>42)</b> Describe how an OS allocates and deallocates memory.</h4>
        </section>
        <section>
            <ol><li>The OS determines the process's memory requirements.</li><li>It finds suitable free memory and allocates it.</li><li>It records which memory areas are in use.</li><li>When the process finishes, its memory is deallocated and marked as free.</li></ol>
            <p><b>[4]</b></p>
        </section>
    </section>

    <!-- 43 -->
    <section>
        <section>
            <h4><b>43)</b> Explain paging.</h4>
        </section>
        <section>
            Paging divides a process into fixed-size pages and physical memory into fixed-size frames. Pages can be placed in non-contiguous frames, and a page table records the mapping between pages and frames.
            <p><b>[4]</b></p>
        </section>
    </section>

    <!-- 44 -->
    <section>
        <section>
            <h4><b>44)</b> State one advantage and one disadvantage of paging.</h4>
        </section>
        <section>
            <b>Advantage:</b> A process does not need one contiguous area of memory, so available frames can be used efficiently.<br><b>Disadvantage:</b> Page tables require memory and paging can introduce internal fragmentation.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 45 -->
    <section>
        <section>
            <h4><b>45)</b> Explain segmentation.</h4>
        </section>
        <section>
            Segmentation divides a program into logical, variable-sized sections called segments, such as code, data, stack and heap. Each segment can be allocated separately.
            <p><b>[3]</b></p>
        </section>
    </section>

    <!-- 46 -->
    <section>
        <section>
            <h4><b>46)</b> State a key difference between paging and segmentation.</h4>
        </section>
        <section>
            Paging uses fixed-size pages and frames, whereas segmentation uses variable-sized logical sections that reflect parts of a program such as code, data and stack.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 47 -->
    <section>
        <section>
            <h4><b>47)</b> What is virtual memory?</h4>
        </section>
        <section>
            Virtual memory uses secondary storage as an extension of main memory. It allows programs or processes requiring more memory than the available RAM to operate.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 48 -->
    <section>
        <section>
            <h4><b>48)</b> Explain what happens during a page fault.</h4>
        </section>
        <section>
            A page fault occurs when a required page is not currently in RAM. The OS locates the page in secondary storage, finds a suitable frame, may move another page out of RAM, loads the required page and then continues execution.
            <p><b>[4]</b></p>
        </section>
    </section>

    <!-- 49 -->
    <section>
        <section>
            <h4><b>49)</b> Explain why virtual memory can reduce performance.</h4>
        </section>
        <section>
            Virtual memory uses secondary storage when RAM is insufficient. Secondary storage is much slower than RAM, so moving pages between storage and RAM increases access time. Excessive paging can lead to thrashing.
            <p><b>[3]</b></p>
        </section>
    </section>

    <!-- 50 -->
    <section>
        <section>
            <h4><b>50)</b> Define a stack and explain a stack frame.</h4>
        </section>
        <section>
            A stack is a memory structure that uses LIFO (Last In, First Out). A stack frame is a section of the call stack containing information associated with a particular function or procedure call.
            <p><b>[3]</b></p>
        </section>
    </section>

    <!-- 51 -->
    <section>
        <section>
            <h4><b>51)</b> State information that can be stored in a stack frame.</h4>
        </section>
        <section>
            A stack frame can contain parameters, local variables, return information and other information needed to resume execution.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 52 -->
    <section>
        <section>
            <h4><b>52)</b> Define an interrupt.</h4>
        </section>
        <section>
            An interrupt is a signal or event that causes the CPU to temporarily stop its current activity so that the event can be dealt with.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 53 -->
    <section>
        <section>
            <h4><b>53)</b> Give two examples of events that can generate interrupts.</h4>
        </section>
        <section>
            <ul><li>A keyboard event</li><li>A timer interrupt</li></ul>
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 54 -->
    <section>
        <section>
            <h4><b>54)</b> Explain why a timer interrupt is useful in multitasking.</h4>
        </section>
        <section>
            A timer interrupt can notify the operating system that a process's time quantum has expired. The OS can then suspend the current process, save its state and schedule another process.
            <p><b>[3]</b></p>
        </section>
    </section>

    <!-- 55 -->
    <section>
        <section>
            <h4><b>55)</b> What is an Interrupt Service Routine (ISR)?</h4>
        </section>
        <section>
            An Interrupt Service Routine is a routine used to deal with a particular interrupt.
            <p><b>[1]</b></p>
        </section>
    </section>

    <!-- 56 -->
    <section>
        <section>
            <h4><b>56)</b> Describe the main stages of interrupt handling.</h4>
        </section>
        <section>
            <ol><li>An interrupt occurs.</li><li>The current process is suspended.</li><li>The current context/state is saved.</li><li>The interrupt is serviced by the appropriate ISR.</li><li>The context is restored.</li><li>The interrupted process can resume.</li></ol>
            <p><b>[5]</b></p>
        </section>
    </section>

    <!-- 57 -->
    <section>
        <section>
            <h4><b>57)</b> What is utility software?</h4>
        </section>
        <section>
            Utility software is software designed to perform maintenance, management, protection or optimisation tasks on a computer system. In the supplied 1.2 material it is treated as supporting knowledge rather than a specifically listed 1.2 requirement.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 58 -->
    <section>
        <section>
            <h4><b>58)</b> State two functions of antivirus software.</h4>
        </section>
        <section>
            Antivirus software can detect malicious software and remove or quarantine it.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 59 -->
    <section>
        <section>
            <h4><b>59)</b> Explain the purpose of file compression.</h4>
        </section>
        <section>
            File compression reduces the amount of storage space required by a file and can reduce the time needed to transfer the file.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 60 -->
    <section>
        <section>
            <h4><b>60)</b> Explain the purpose of backup software.</h4>
        </section>
        <section>
            Backup software creates copies of data so that the data can be recovered after accidental deletion, hardware failure, malware or corruption.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 61 -->
    <section>
        <section>
            <h4><b>61)</b> Explain why disk defragmentation is associated mainly with traditional HDDs.</h4>
        </section>
        <section>
            Disk defragmentation reorganises fragmented files so that related data is stored more efficiently. On traditional HDDs this can reduce disk-head movement and potentially improve access performance.
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 62 -->
    <section>
        <section>
            <h4><b>62)</b> Exam question: Explain why virtual memory is useful but can reduce performance.</h4>
        </section>
        <section>
            Virtual memory is useful because it allows programs requiring more memory than the available RAM to operate by using secondary storage as an extension of main memory. However, secondary storage is much slower than RAM, so transferring pages between storage and RAM increases access time. Excessive paging can cause thrashing and significantly reduce performance.
            <p><b>[4]</b></p>
        </section>
    </section>

    <!-- 63 -->
    <section>
        <section>
            <h4><b>63)</b> Exam question: Explain how Round Robin scheduling supports multitasking.</h4>
        </section>
        <section>
            Each ready process is given a fixed time quantum in turn. When its quantum expires, the process can be suspended and its context saved, allowing another process to run. Unfinished processes return to the ready queue for another time slice. This allows multiple processes to make progress and appear to execute simultaneously.
            <p><b>[4]</b></p>
        </section>
    </section>

    <!-- 64 -->
    <section>
        <section>
            <h4><b>64)</b> Exam question: Explain the relationship between multitasking and context switching.</h4>
        </section>
        <section>
            Multitasking is the ability of the operating system to manage multiple processes so they appear to execute simultaneously. Context switching is one mechanism used to achieve this: the OS saves the state of one process and loads the state of another so CPU execution can move between processes.
            <p><b>[4]</b></p>
        </section>
    </section>

    <!-- 65 -->
    <section>
        <section>
            <h4><b>65)</b> Exam question: Compare FCFS, SJF and Round Robin scheduling.</h4>
        </section>
        <section>
            <b>FCFS:</b> processes are selected in arrival order; it is simple but a long process can delay others.<br><b>SJF:</b> the process with the shortest expected execution time is selected first; it can reduce average waiting time but long processes may starve.<br><b>Round Robin:</b> processes receive fixed time quanta in turn; it is fair and suitable for interactive systems but context switching creates overhead.
            <p><b>[6]</b></p>
        </section>
    </section>

    <!-- 66 -->
    <section>
        <section>
            <h4><b>66)</b> Exam question: Explain paging, segmentation and virtual memory.</h4>
        </section>
        <section>
            <b>Paging</b> divides processes into fixed-size pages and memory into fixed-size frames; pages can be stored in non-contiguous frames.<br><b>Segmentation</b> divides a program into variable-sized logical sections such as code, data, stack and heap.<br><b>Virtual memory</b> uses secondary storage as an extension of RAM, allowing programs to run when there is insufficient physical memory, although it can reduce performance because secondary storage is slower than RAM.
            <p><b>[6]</b></p>
        </section>
    </section>

    <!-- 67 -->
    <section>
        <section>
            <h4><b>67)</b> Exam question: Explain how an operating system manages a process from creation until completion.</h4>
        </section>
        <section>
            The OS creates and manages the process and places it in the Ready state when it is waiting for CPU time. The scheduler selects it to enter the Running state. If it needs to wait for an event such as I/O, it enters the Waiting/Blocked state and later returns to Ready. When execution finishes or the process is stopped, it enters the Terminated state and its resources can be released.
            <p><b>[6]</b></p>
        </section>
    </section>

    <!-- 68 -->
    <section>
        <section>
            <h4><b>68)</b> Exam question: Explain how interrupts support process scheduling.</h4>
        </section>
        <section>
            An interrupt signals the CPU that an event needs attention. For example, a timer interrupt can occur when a process's time quantum expires. The OS can suspend the current process, save its context, service the interrupt and then schedule another ready process. This allows the OS to regain control of the CPU and support multitasking.
            <p><b>[5]</b></p>
        </section>
    </section>

    <!-- FINAL EXAM CHECKLIST -->
    <section>
        <h3>1.2 Operating Systems – Exam Checklist</h3>
        <ul>
            <li>Operating system purpose and core roles</li>
            <li>GUI and CLI with advantages and disadvantages</li>
            <li>User, peripheral, process and memory management</li>
            <li>Multitasking, time sharing and context switching</li>
            <li>Processes and process states</li>
            <li>FCFS, SJF, Round Robin and multi-level queues</li>
            <li>Starvation and scheduling problems</li>
            <li>Memory allocation and deallocation</li>
            <li>Paging and page tables</li>
            <li>Segmentation</li>
            <li>Virtual memory and page faults</li>
            <li>Stacks and stack frames</li>
            <li>Interrupts and Interrupt Service Routines</li>
            <li>Supporting utility-software knowledge</li>
        </ul>
    </section>

    <section>
        <h3>Important Exam Traps</h3>
        <ul>
            <li>Multitasking does <b>not</b> require multiple CPUs; a single core can rapidly switch between processes.</li>
            <li>Do not confuse a <b>program</b> with a <b>process</b>.</li>
            <li>Round Robin is not FCFS; unfinished processes return for additional time slices.</li>
            <li>Paging and virtual memory are related but are <b>not identical</b>.</li>
            <li>Segmentation uses <b>variable-sized logical sections</b>; paging uses <b>fixed-size pages and frames</b>.</li>
            <li>Multitasking is the capability; context switching is one mechanism used to switch processes.</li>
            <li>Virtual memory does not make memory faster; secondary storage is much slower than RAM.</li>
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
