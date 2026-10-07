
(function(){
    'use strict';
    function init(){
        document.querySelectorAll('.sde-countdown[data-next-class]').forEach(function(el){
            var target = new Date(el.getAttribute('data-next-class').replace(' ','T'));
            if (isNaN(target.getTime())) return;
            function tick(){
                var diff = target.getTime()-Date.now();
                if(diff<=0){ el.textContent='Starting now'; return; }
                var sec=Math.floor(diff/1000);
                var d=Math.floor(sec/86400); sec%=86400;
                var h=Math.floor(sec/3600); sec%=3600;
                var m=Math.floor(sec/60); var s=sec%60;
                el.textContent=(d>0?String(d).padStart(2,'0')+'d ':'')+
                    String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
            }
            tick(); setInterval(tick,1000);
        });
    }
    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
})();
