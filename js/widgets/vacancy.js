var gethhkVacancy = function(siteURL = "", targetID = "") {
        let hhkVacancy = document.getElementById(targetID);

        if (hhkVacancy && siteURL !== "") {
            fetch(siteURL + '/api/v1/widget/vacancy')
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(function(data) {
                    if (data.hasVacancy !== undefined) {
                        hhkVacancy.dataset.hasVacancy = data.hasVacancy;
                        hhkVacancy.textContent = data.hasVacancy ? 'Yes' : 'No';

                        // Manually trigger a 'change' event
                        var event = new Event('change');
                        hhkVacancy.dispatchEvent(event);
                        
                        return data.hasVacancy;
                    }else{
                        console.error("Vacancy data is undefined or not in the expected format.");
                        return null;
                    }
                })
                .catch(function(error) {
                    console.error("Error fetching vacancy data: " + error);
                });
        }
}