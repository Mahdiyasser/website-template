// Clear client-side inputs and also clear server-side saved form data
function clearForm(){
  document.querySelectorAll('#postForm input[type=text], #postForm input[type=date], #postForm input[type=time], #postForm textarea').forEach(el=>el.value='');
  document.querySelectorAll('#postForm input[type=file]').forEach(el=>el.value=null);
  // request server to clear session-saved form data
  window.location = window.location.pathname + '?clear=1';
}
