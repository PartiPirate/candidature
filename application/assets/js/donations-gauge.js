var showDonationsGauge = function(autoRetry){
    var formatNb = function(nb){
        return nb.toLocaleString('fr-FR');
    }

    $.ajax({
        url: 'https://don.partipirate.org/api/getGauge.php?from_date=2017-01-01&to_date=2020-01-01&amount_path=project%3EadditionalDonation&search=%22project%22:%7B%22code%22:%22BUD_ELECTION_2019%22',
        success: function(data){
            var allObjectives = [
                {"value": 27000, "description": "Nous pouvons imprimer 4&nbsp;600&nbsp;000 bulletins, soit pour environ 10&nbsp;% de l'électorat."},
                {"value": 135000, "description": "Nous pouvons imprimer assez de bulletins pour la moitié de l'électorat."},
                {"value": 270000, "description": "Nous pouvons imprimer assez de bulletins pour tous les électeurs."},
                {"value": 400000, "description": "Nous pouvons faire campagne avec affiches, brochures, etc."},
            ];

            var current = data.gau_amount;
        
            var objectives = [];
            for (var i=0;i<allObjectives.length;i++){
            	objectives.push(allObjectives[i]);
            	if (current < allObjectives[i].value)
            		break;
            }

            var total = objectives[objectives.length-1].value;
            var obj = Math.min(100, (((current) * 100)/total));

            var currentStep = 0;
            for (var i=0;i<objectives.length;i++){
                if (current > objectives[i].value){
                    currentStep++;
                }
            }

            var currentStepValue = objectives[currentStep].value;

            var html = '<div id="gauge" class="progress bg-info">'+
                    '<div class="progress-bar" style="width:' + (100*currentStepValue/total) + '%"></div>'+
                    '<div class="progress-bar active" role="progressbar" aria-valuenow="' + obj + '" aria-valuemin="0" aria-valuemax="100" style="width:' + obj + '%">'+formatNb(current)+'&nbsp;€</div>'+
                    '</div>'+
                    '<div class="gauge-steps">';

                for (var i=0;i<objectives.length;i++){
                    var state = 'locked';
                    if (i < currentStep)
                        state = 'achieved';
                    else if (i == currentStep)
                        state = 'current';
                    var o = objectives[i];
                    html +=  '<div class="gauge-step '+state;

                    html += '" style="left: '+(100*o.value/total)+'%;">'+
                        '<div class="label">';
                        switch (state){
                            case 'locked': html += 'Verrouillé&hellip;'; break;
                            case 'current': html += 'Prochain objectif&nbsp;!'; break;
                            case 'achieved': html += 'Objectif atteint'; break;
                        }
                        html += '</div>'+
                        '<div class="value">'+formatNb(o.value)+'&nbsp;€</div>'+
                        '<div class="description">'+o.description+'</div>'+
                    '</div>'
                }

            html += '</div></div>';

            $("#donations-gauge-wrapper").html(html);
        },
        error: function(){
            $("#donations-gauge-wrapper").html('<div style="text-align: center;">Erreur lors du chargement de la jauge des dons. <a onclick="showDonationsGauge(false);" style="cursor: pointer;">Réessayer</a></div>');

            if (autoRetry){
                setTimeout(function(){
                    showDonationsGauge(true);
                }, 5000);
            }
        },
        dataType: 'JSON'
    });

}
$(function(){
    showDonationsGauge(true);
});