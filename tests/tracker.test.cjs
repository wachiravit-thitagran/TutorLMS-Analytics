const {test}=require('node:test'); const assert=require('node:assert/strict'); const vm=require('node:vm'); const fs=require('node:fs');
test('exit event is JSON authenticated and sent once per pagehide lifecycle',async()=>{
 const events={};const calls=[];const context={TutorLMSAnalyticsData:{rest_url:'/track',nonce:'test-nonce',course_id:12},window:{location:{href:'http://example.test'},addEventListener:(k,f)=>events[k]=f},document:{querySelector:()=>null,querySelectorAll:()=>[]},navigator:{sendBeacon:()=>{throw Error('unexpected beacon')}},fetch:(...args)=>{calls.push(args);return Promise.resolve({ok:true})},console,Date,setInterval,clearInterval};
 vm.runInNewContext(fs.readFileSync('assets/tracker.js','utf8'),context);assert.equal(typeof events.pagehide,'function');events.pagehide();events.pagehide();await Promise.resolve();
 assert.equal(calls.length,2);const opts=calls[1][1];assert.equal(opts.keepalive,true);assert.equal(opts.headers['X-WP-Nonce'],'test-nonce');assert.equal(opts.headers['Content-Type'],'application/json');const data=JSON.parse(opts.body);assert.equal(data.event_type,'page_exit');assert.equal(data.course_id,12);
});

test('BFCache restore restarts exactly one lesson heartbeat',()=>{
 const events={},intervals=new Set();let serial=0;const ctx={TutorLMSAnalyticsData:{rest_url:'/track',nonce:'n',course_id:1},window:{location:{href:'/lesson'},addEventListener:(k,f)=>events[k]=f},document:{querySelector:()=>({getAttribute:k=>k==='data-course-id'?'1':'2'}),querySelectorAll:()=>[{}]},fetch:()=>Promise.resolve({ok:true}),console,Date,setInterval:()=>{intervals.add(++serial);return serial},clearInterval:id=>intervals.delete(id)};
 vm.runInNewContext(fs.readFileSync('assets/tracker.js','utf8'),ctx);assert.equal(intervals.size,1);events.pagehide();assert.equal(intervals.size,0);events.pageshow({persisted:true});assert.equal(intervals.size,1);events.pageshow({persisted:true});assert.equal(intervals.size,1);
});
