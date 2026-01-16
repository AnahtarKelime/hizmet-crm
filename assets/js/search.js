document.addEventListener('DOMContentLoaded', () => {
        const serviceInput = document.getElementById('service-search');
        const locationInput = document.getElementById('location-search');
        const serviceResults = document.getElementById('service-results');
        const locationResults = document.getElementById('location-results');
        const selectedServiceSlug = document.getElementById('selected-service-slug');
        const selectedLocationSlug = document.getElementById('selected-location-slug');
        const findButton = document.getElementById('btn-find-service');
        const searchIcon = document.getElementById('search-icon');
        const searchSpinner = document.getElementById('search-spinner');

        // API Base URL'ini dinamik olarak bul
        // Bu script'in (search.js) bulunduğu yerden yola çıkarak kök dizini buluruz.
        let apiBaseUrl = 'ajax/'; // Varsayılan
        const scripts = document.getElementsByTagName('script');
        for (let script of scripts) {
                if (script.src.includes('assets/js/search.js')) {
                        // script.src örneğin: http://localhost/hizmet-crm/assets/js/search.js
                        // Buradan http://localhost/hizmet-crm/ kısmını alıp ajax/ ekliyoruz.
                        apiBaseUrl = script.src.replace('assets/js/search.js', 'ajax/');
                        break;
                }
        }

        // Debounce: Her tuşa basıldığında istek atmamak için bekleme süresi
        function debounce(func, wait) {
                let timeout;
                return function (...args) {
                        clearTimeout(timeout);
                        timeout = setTimeout(() => func.apply(this, args), wait);
                };
        }

        // Sonuçları Ekrana Basma Fonksiyonu
        function renderServiceResults(data, isPopular = false) {
                serviceResults.innerHTML = '';

                if (isPopular) {
                        const header = document.createElement('li');
                        header.className = 'px-4 py-2 text-xs font-bold text-slate-400 bg-slate-50 uppercase tracking-wider';
                        header.textContent = 'Popüler Hizmetler';
                        serviceResults.appendChild(header);
                }

                if (Array.isArray(data) && data.length > 0) {
                        serviceResults.classList.remove('hidden');
                        data.forEach(item => {
                                const li = document.createElement('li');
                                li.className = 'px-4 py-3 hover:bg-slate-50 cursor-pointer flex items-center gap-3 transition-colors border-b border-slate-100 last:border-0';
                                li.innerHTML = `
                        <span class="material-symbols-outlined text-slate-400">${item.icon || 'work'}</span>
                        <div class="flex flex-col">
                            <span class="font-medium text-slate-700">${item.name}</span>
                            ${item.matched_keyword ? `<span class="text-xs text-slate-400">"${item.matched_keyword}" sonucunda</span>` : ''}
                        </div>
                    `;
                                li.onclick = () => {
                                        serviceInput.value = item.name;
                                        if (selectedServiceSlug) selectedServiceSlug.value = item.slug;
                                        serviceResults.classList.add('hidden');
                                };
                                serviceResults.appendChild(li);
                        });
                } else if (!isPopular) {
                        serviceResults.classList.remove('hidden');
                        serviceResults.innerHTML = '<li class="px-4 py-3 text-slate-500 text-sm text-center">Sonuç bulunamadı.</li>';
                } else {
                        serviceResults.classList.add('hidden');
                }
        }

        // Hizmet Arama
        if (serviceInput) {
                console.log('Hizmet arama aktif'); // Kontrol logu

                // Odaklanınca Popüler Hizmetleri Göster
                serviceInput.addEventListener('focus', () => {
                        if (serviceInput.value.trim() === '' && typeof popularServicesData !== 'undefined') {
                                renderServiceResults(popularServicesData, true);
                        }
                });

                serviceInput.addEventListener('input', debounce(async (e) => {
                        const query = e.target.value;

                        // Yazı değiştiği an seçili slug'ı temizle, kullanıcıyı seçmeye zorla
                        if (selectedServiceSlug) selectedServiceSlug.value = '';

                        if (query.length === 0) {
                                if (typeof popularServicesData !== 'undefined') {
                                        renderServiceResults(popularServicesData, true);
                                } else {
                                        serviceResults.classList.add('hidden');
                                }
                                return;
                        }

                        if (query.length < 2) {
                                serviceResults.classList.add('hidden');
                                return;
                        }

                        if (searchIcon && searchSpinner) {
                                searchIcon.classList.add('hidden');
                                searchSpinner.classList.remove('hidden');
                        }

                        try {
                                const res = await fetch(`${apiBaseUrl}search.php?type=service&q=${encodeURIComponent(query)}`);
                                const text = await res.text();

                                if (!res.ok) {
                                        console.error('Sunucu hatası:', res.status, text);
                                        return;
                                }

                                let data;
                                try {
                                        data = JSON.parse(text);
                                } catch (jsonError) {
                                        console.error('JSON Hatası:', jsonError, 'Sunucu Yanıtı:', text);
                                        // Eğer JSON parse edilemezse (örn: PHP hatası metin olarak döndüyse)
                                        return;
                                }

                                if (data.error) {
                                        console.error('Veritabanı hatası:', data.error);
                                        return;
                                }

                                renderServiceResults(data);
                        } catch (err) {
                                console.error('Arama hatası:', err);
                        } finally {
                                if (searchIcon && searchSpinner) {
                                        searchIcon.classList.remove('hidden');
                                        searchSpinner.classList.add('hidden');
                                }
                        }
                }, 300));
        }

        // Lokasyon Arama
        if (locationInput) {
                console.log('Lokasyon arama aktif'); // Kontrol logu
                locationInput.addEventListener('input', debounce(async (e) => {
                        const query = e.target.value;

                        // Yazı değiştiği an seçili slug'ı temizle
                        if (selectedLocationSlug) selectedLocationSlug.value = '';

                        if (query.length < 2) {
                                locationResults.classList.add('hidden');
                                return;
                        }

                        try {
                                const res = await fetch(`${apiBaseUrl}search.php?type=location&q=${encodeURIComponent(query)}`);
                                const text = await res.text();

                                if (!res.ok) {
                                        console.error('Sunucu hatası:', res.status, text);
                                        return;
                                }

                                let data;
                                try {
                                        data = JSON.parse(text);
                                } catch (jsonError) {
                                        console.error('JSON Hatası:', jsonError, 'Sunucu Yanıtı:', text);
                                        return;
                                }

                                if (data.error) {
                                        console.error('Veritabanı hatası:', data.error);
                                        return;
                                }

                                locationResults.innerHTML = '';
                                if (Array.isArray(data) && data.length > 0) {
                                        locationResults.classList.remove('hidden');
                                        data.forEach(item => {
                                                const li = document.createElement('li');
                                                li.className = 'px-4 py-3 hover:bg-slate-50 cursor-pointer flex items-center gap-3 transition-colors border-b border-slate-100 last:border-0';
                                                // Gösterim: Mahalle, İlçe / İl
                                                const displayText = `${item.neighborhood}, ${item.district} / ${item.city}`;
                                                li.innerHTML = `
                            <span class="material-symbols-outlined text-slate-400">location_on</span>
                            <div class="flex flex-col">
                                <span class="font-medium text-slate-700 text-sm">${item.neighborhood}</span>
                                <span class="text-xs text-slate-400">${item.district} / ${item.city}</span>
                            </div>
                        `;
                                                li.onclick = () => {
                                                        locationInput.value = displayText;
                                                        if (selectedLocationSlug) selectedLocationSlug.value = item.slug; // Slug'ı kaydet
                                                        locationResults.classList.add('hidden');
                                                };
                                                locationResults.appendChild(li);
                                        });
                                } else {
                                        locationResults.classList.add('hidden');
                                }
                        } catch (err) {
                                console.error('Lokasyon hatası:', err);
                        }
                }, 300));
        }

        // Dışarı tıklandığında listeleri kapat
        document.addEventListener('click', (e) => {
                if (serviceInput && !serviceInput.contains(e.target) && !serviceResults.contains(e.target)) {
                        serviceResults.classList.add('hidden');
                }
                if (locationInput && !locationInput.contains(e.target) && !locationResults.contains(e.target)) {
                        locationResults.classList.add('hidden');
                }
        });

        // Butona tıklama ve yönlendirme
        if (findButton) {
                findButton.addEventListener('click', () => {
                        const service = selectedServiceSlug ? selectedServiceSlug.value : '';
                        const location = selectedLocationSlug ? selectedLocationSlug.value : '';

                        if (!service) {
                                alert('Lütfen bir hizmet seçiniz.');
                                return;
                        }

                        let url = `teklif-al.php?service=${encodeURIComponent(service)}`;
                        if (location) {
                                url += `&location=${encodeURIComponent(location)}`;
                        }
                        window.location.href = url;
                });
        }
});