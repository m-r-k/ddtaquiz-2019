YUI.add("moodle-mod_ddtaquiz-dragdrop",function(e,t){var n={ACTIONAREA:".actions",ACTIVITY:"activity",ACTIVITYINSTANCE:"activityinstance",CONTENT:"content",COURSECONTENT:"mod-ddtaquiz-edit-content",EDITINGMOVE:"editing_move",ICONCLASS:"iconsmall",JUMPMENU:"jumpmenu",LEFT:"left",LIGHTBOX:"lightbox",MOVEDOWN:"movedown",MOVEUP:"moveup",PAGE:"page",PAGECONTENT:"page-content",RIGHT:"right",SECTION:"slots",SECTIONADDMENUS:"section_add_menus",SECTIONHANDLE:"section-handle",SLOTS:"slots",SUMMARY:"summary",SECTIONDRAGGABLE:"sectiondraggable"},r={PAGE:"li.page",SLOT:"li.slot"},i=function(){i.superclass.constructor.apply(this,arguments)};e.extend(i,M.core.dragdrop,{sectionlistselector:null,initializer:function(){this.groups=[n.SECTIONDRAGGABLE],this.samenodeclass="section",this.parentnodeclass="slots";if(e.Node.one("."+n.JUMPMENU))return!1;this.sectionlistselector="li.section";if(this.sectionlistselector){this.sectionlistselector="."+n.COURSECONTENT+" "+this.sectionlistselector,this.setup_for_section(this.sectionlistselector);var t=new e.DD.Delegate({container:"."+n.COURSECONTENT,nodes:"."+n.SECTIONDRAGGABLE,target:!0,handles:["."+n.LEFT],dragConfig:{groups:this.groups}});t.dd.plug(e.Plugin.DDProxy,{moveOnEnd:!1}),t.dd.plug(e.Plugin.DDConstrained,{constrain:"#"+n.PAGECONTENT,stickY:!0}),t.dd.plug(e.Plugin.DDWinScroll)}},setup_for_section:function(t){e.Node.all(t).each(function(t){var r=e.Moodle.core_course.util.section.getId(t);if(r>0){var i=t.one("."+n.RIGHT+" a."+n.MOVEDOWN),s=t.one("."+n.RIGHT+" a."+n.MOVEUP),o=M.util.get_string("movesection","moodle",r),u=t.one("."+n.LEFT);(i||s)&&u&&(u.setStyle("cursor","move"),u.appendChild(this.get_drag_handle(o,n.SECTIONHANDLE,"icon",!0)),s&&s.remove(),i&&i.remove(),t.addClass(n.SECTIONDRAGGABLE))}},this)},drag_start:function(t){var r=t.target,i=e.Node.create('<ul class="slots"></ul>'),s=e.Node.create('<ul class="section"></ul>');s.setStyle("margin",0),s.setContent(r.get("node").get("innerHTML")),i.appendChild(s),r.get("dragNode").setContent(i),r.get("dragNode").addClass(n.COURSECONTENT)},drag_dropmiss:function(e){this.drop_hit(e)},get_section_index:function(t){var r="."+n.COURSECONTENT+" li.section",i=e.all(r),s=i.indexOf(t),o=i.indexOf(e.one("#section-0"));return s-o},drop_hit:function(t){var r=t.drag,i=r.get("node"),s=e.Moodle.core_course.util.section.getId(i),o=s,u=this.get_section_index(i),a=u;if(s===u)return;o>a&&(o=u,a=s),r.get("dragNode").removeClass(n.COURSECONTENT);var f=e.Node.all(this.sectionlistselector),l=M.util.add_lightbox(e,i),c={},h=this.get("config").pageparams,p;for(p in h){if(!h.hasOwnProperty(p))continue;c[p]=h[p]}c.sesskey=M.cfg.sesskey,c.courseid=this.get("courseid"),c.ddtaquizid=this.get("ddtaquizid"),c["class"]="section",c.field="move",c.id=s,c.value=u;var d=M.cfg.wwwroot+this.get("ajaxurl");e.io(d,{method:"POST",data:c,on:{start:function(){l.show()},success:function(t,n){try{var r=e.JSON.parse(n.responseText);r.error&&new M.core.ajaxException(r),M.mod_ddtaquiz.edit.process_sections(e,f,r,o,a)}catch(i){}var s,u=!1;do{u=!1;for(s=o;s<=a;s++)if(e.Moodle.core_course.util.section.getId(f.item(s-1))>e.Moodle.core_course.util.section.getId(f.item(s))){var c=f.item(s-1).get("id");f.item(s-1).set("id",f.item(s).get("id")),f.item(s).set("id",c),M.mod_ddtaquiz.edit.swap_sections(e,s-1,s),u=!0}a-=1}while(u);window.setTimeout(function(){l.hide()},250)},failure:function(e,t){this.ajax_failure(t),l.hide()}},context:this})}},{NAME:"mod_ddtaquiz-dragdrop-section",ATTRS:{courseid:{value:null},ddtaquizid:{value:null},ajaxurl:{value:0},config:{value:0}}}),M.mod_ddtaquiz=M.mod_ddtaquiz||{},M.mod_ddtaquiz.init_section_dragdrop=function(e){new i(e)};var s=function(){s.superclass.constructor.apply(this,arguments)};e.extend(s,M.core.dragdrop,{initializer:function(){this.groups=["resource"],this.samenodeclass=n.ACTIVITY,this.parentnodeclass=n.SECTION,this.samenodelabel={identifier:"dragtoafter",component:"ddtaquiz"},this.parentnodelabel={identifier:"dragtostart",component:"ddtaquiz"},this.setup_for_section();var t="li."+n.ACTIVITY,r=new e.DD.Delegate({container:"."+n.COURSECONTENT,nodes:t,target:!0,handles:["."+n.EDITINGMOVE],dragConfig:{groups:this.groups}});r.dd.plug(e.Plugin.DDProxy,{moveOnEnd:!1,cloneNode:!0}),r.dd.plug(e.Plugin.DDConstrained,{constrain:"#"+n.SLOTS}),r.dd.plug(e.Plugin.DDWinScroll),M.mod_ddtaquiz.ddtaquizbase.register_module(this),M.mod_ddtaquiz.dragres=this},setup_for_section:function(){e.Node.all(".mod-ddtaquiz-edit-content ul.slots ul.section").each(function(t){t.setAttribute("data-draggroups",this.groups.join(" ")),new e.DD.Drop({node:t,groups:this.groups,padding:"20 0 20 0"}),this.setup_for_resource("li.activity")},this)},setup_for_resource:function(t){e.Node.all(t).each(function(e){var t=e.one("a."+n.EDITINGMOVE);if(t){var r=this.get_drag_handle(M.util.get_string("move","moodle"),n.EDITINGMOVE,n.ICONCLASS,!0);t.replace(r)}},this)},drag_start:function(e){var t=e.target;t.get("dragNode").setContent(t.get("node").get("innerHTML")),t.get("dragNode").all(".icon").setStyle("vertical-align","baseline")},drag_dropmiss:function(e){this.drop_hit(e)},drop_hit:function(t){var i=t.drag,s=i.get("node"),o=t.drop.get("node"),u=s.one(n.ACTIONAREA),a=M.util.add_spinner(e,u),f={},l=this.get("config").pageparams,c;for(c in l)f[c]=l[c];f.sesskey=M.cfg.sesskey,f.courseid=this.get("courseid"),f.ddtaquizid=this.get("ddtaquizid"),f["class"]="resource",f.field="move",f.id=Number(e.Moodle.mod_ddtaquiz.util.slot.getId(s)),f.sectionId=e.Moodle.core_course.util.section.getId(o.ancestor("li.section",!0));var h=s.previous(r.SLOT);h&&(f.previousid=Number(e.Moodle.mod_ddtaquiz.util.slot.getId(h)));var p=s.previous(r.PAGE);p&&(f.page=Number(e.Moodle.mod_ddtaquiz.util.page.getId(p)));var d=M.cfg.wwwroot+this.get("ajaxurl");e.io(d,{method:"POST",data:f,on:{start:function(){this.lock_drag_handle(i,n.EDITINGMOVE),a.show()},success:function(t,r){var o=e.JSON.parse(r.responseText),u={element:s,visible:o.visible};M.mod_ddtaquiz.ddtaquizbase.invoke_function("set_visibility_resource_ui",u),this.unlock_drag_handle(i,n.EDITINGMOVE),window.setTimeout(function(){a.hide()},250
),M.mod_ddtaquiz.resource_toolbox.reorganise_edit_page()},failure:function(e,t){this.ajax_failure(t),this.unlock_drag_handle(i,n.SECTIONHANDLE),a.hide(),window.location.reload(!0)}},context:this})},global_drop_over:function(e){if(!e.drop||!e.drop.inGroup(this.groups))return;var t=e.drag.get("node"),n=e.drop.get("node");this.lastdroptarget=e.drop;if(n.hasClass(this.samenodeclass)){var r;this.goingup?r="before":r="after",n.insert(t,r)}else(n.hasClass(this.parentnodeclass)||n.test('[data-droptarget="1"]'))&&!n.contains(t)&&(this.goingup?n.append(t):n.prepend(t));this.drop_over(e)}},{NAME:"mod_ddtaquiz-dragdrop-resource",ATTRS:{courseid:{value:null},ddtaquizid:{value:null},ajaxurl:{value:0},config:{value:0}}}),M.mod_ddtaquiz=M.mod_ddtaquiz||{},M.mod_ddtaquiz.init_resource_dragdrop=function(e){new s(e)}},"@VERSION@",{requires:["base","node","io","dom","dd","dd-scroll","moodle-core-dragdrop","moodle-core-notification","moodle-mod_ddtaquiz-ddtaquizbase","moodle-mod_ddtaquiz-util-base","moodle-mod_ddtaquiz-util-page","moodle-mod_ddtaquiz-util-slot","moodle-course-util"]});
