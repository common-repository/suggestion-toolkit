
class productService{
	Cart = [];

	constructor(api_url){
		this.api = api_url;
	}
	
	toggleToCart(id, price){
		let index = -1;
		this.Cart.forEach((item, k)=>{ if(item.id==id){ index=k; } });
		if (index > -1) {
		  this.Cart.splice(index, 1);
		}else{
			this.Cart.push({'id': id, 'price': price});
		}
		
		if(document.querySelector('#ps-product-total-num')){
			document.querySelector('#ps-product-total-num').innerHTML=this.Cart.length;
		}else{
			window.alert("Please, register first");
		}
		
		let Total = 0;
		this.Cart.forEach((item, index)=>{ Total += item.price; });
		document.querySelector('#ps-product-total-price').innerHTML= "$"+Total.toFixed(2);
		
		return (index > -1);
	}
	
	inCart(id){
		return (this.Cart.indexOf(id)>-1);
	}
	
	getTotal(){
	}
	
	proceedCheckout(user_id, slug, key1, key2){
		this.$("#checkout-error1").classList.add('hidden');
		this.$("#checkout-error2").classList.add('hidden');
		
		if(this.Cart.length==0){
			this.$("#checkout-error1").classList.remove('hidden');
			return false;
		}
		
		//console.log(user_id);
		//return true;
		
		let formData = new FormData();
		formData.append('user_id', user_id);
		formData.append('slug', slug);
		formData.append('return_url', window.location);
		this.Cart.forEach((item, k)=>{ 
			formData.append('prod['+k+']', item.id);
			formData.append('price['+k+']', item.price);
		});
		
		fetch(this.api+'order', {
		  method: 'POST',
		  body: formData
		}).then(response => response.json()).then(result=>{
			if(result.status=='ok'){
				window.location = result.pay_url+"&key1="+key1+"&key2="+key2;
			}else{
				this.$("#checkout-error2").classList.remove('hidden');
			}
		});
	
	}
	
	verifyResend(){
		this.$("#ps-product-login-error").innerHTML = "";
		this.$("#login_email").classList.remove('error');
		this.$("#ps-product-login-form").classList.remove('hidden');
		this.$("#ps-product-verify-form").classList.add('hidden');
	}
	
	verifyRegistrationPlugin(code, email, url){
		let formData = new FormData();
		formData.append('action', 'confirm_registration');
		formData.append('email', email);
		formData.append('code', code);
		formData.append('url', url);
		
		fetch(ajaxurl, {
		  method: 'POST',
		  body: formData
		}).then(response => response.json()).then(result=>{
			if(result.status=='verification'){
				this.$("#ps-product-login-form").classList.add('hidden');
				this.$("#ps-product-verify-form").classList.remove('hidden');
			}
			if(result.status=='ok'){
				this.$("#login_code").classList.remove('error');
				window.location.reload();
			}else{
				this.$("#ps-product-verify-error").innerHTML = result.message;
				this.$("#login_code").classList.add('error');
			}
		});
	}
	
	registerPlugin(email, url){
		let formData = new FormData();
		formData.append('action', 'domain_registration');
		formData.append('email', email);
		formData.append('url', url);
		formData.append('login_url', window.location);
		
		fetch(ajaxurl, {
		  method: 'POST',
		  body: formData
		}).then(response => response.json()).then(result=>{
			if(result.status=='verification'){
				this.$("#ps-product-login-form").classList.add('hidden');
				this.$("#ps-product-verify-form").classList.remove('hidden');
			}
			console.log(result);
			if(result.status=='ok'){
				this.$("#ps-product-login-error").innerHTML = "";
				this.$("#login_email").classList.remove('error');
				window.location.reload();
			}else{
				this.$("#ps-product-login-error").innerHTML = result.message;
				this.$("#login_email").classList.add('error');
			}
		});
		
		
		return 0;
	}
	
	installPlugin(id){
		let formData = new FormData();
		formData.append('action', 'install_plugin');
		formData.append('download_id', id);
		
		fetch(ajaxurl, {
		  method: 'POST',
		  body: formData
		}).then(response => response.json()).then(result=>{
			if(result.status=='ok'){
				window.location.reload();
			}
		});
	}
	
	uninstallPlugin(id){
		let formData = new FormData();
		formData.append('action', 'uninstall_plugin');
		formData.append('download_id', id);
		
		fetch(ajaxurl, {
		  method: 'POST',
		  body: formData
		}).then(response => response.json()).then(result=>{
			if(result.status=='ok'){
				window.location.reload();
			}
		});
	}
	
	updatePlugin(id){
		let formData = new FormData();
		formData.append('action', 'update_plugin');
		formData.append('download_id', id);
		
		fetch(ajaxurl, {
		  method: 'POST',
		  body: formData
		}).then(response => response.json()).then(result=>{
			if(result.status=='ok'){
				window.location.reload();
			}
		});
	}
	
	activatePlugin(plugin){
		let formData = new FormData();
		formData.append('action', 'activate_plugin');
		formData.append('plugin', plugin);
		
		fetch(ajaxurl, {
		  method: 'POST',
		  body: formData
		}).then(response => response.json()).then(result=>{
			if(result.status=='ok'){
				window.location.reload();
			}
		});
	}
	
	deactivatePlugin(plugin){
		let formData = new FormData();
		formData.append('action', 'deactivate_plugin');
		formData.append('plugin', plugin);
		
		fetch(ajaxurl, {
		  method: 'POST',
		  body: formData
		}).then(response => response.json()).then(result=>{
			if(result.status=='ok'){
				window.location.reload();
			}
		});
	}
	
	$(query, root = document){
		if(query[0]=='#'){
			return root.querySelector(query);
		}else{
			return root.querySelectorAll(query);
		}
	}
}

var psInst = new productService(prodService.api_url);
