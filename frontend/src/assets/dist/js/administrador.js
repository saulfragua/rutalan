  function openTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(tab => {
      tab.classList.add('hidden');
    });

    document.getElementById(tabId).classList.remove('hidden');

    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.classList.remove('text-[#c8982e]', 'bg-[#222]');
      btn.classList.add('text-gray-400');
    });

    event.target.classList.add('text-[#c8982e]', 'bg-[#222]');
    event.target.classList.remove('text-gray-400');
  }