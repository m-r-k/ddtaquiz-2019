YUI.add("moodle-mod_ddtaquiz-modform",function(e,t){var n=function(){n.superclass.constructor.apply(this,arguments)};e.extend(n,e.Base,{repaginateCheckbox:null,qppSelect:null,qppInitialValue:0,initializer:function(){this.repaginateCheckbox=e.one("#id_repaginatenow");if(!this.repaginateCheckbox)return;this.qppSelect=e.one("#id_questionsperpage"),this.qppInitialValue=this.qppSelect.get("value"),this.qppSelect.on("change",this.qppChanged,this)},qppChanged:function(){e.later(50,this,function(){this.repaginateCheckbox.get("disabled")||this.repaginateCheckbox.set("checked",this.qppSelect.get("value")!==this.qppInitialValue)})}}),M.mod_ddtaquiz=M.mod_ddtaquiz||{},M.mod_ddtaquiz.modform=M.mod_ddtaquiz.modform||new n,M.mod_ddtaquiz.modform.init=function(){return new n}},"@VERSION@",{requires:["base","node","event"]});
