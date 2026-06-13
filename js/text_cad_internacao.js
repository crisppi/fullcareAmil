 // mudar linhas do relatorio 
 var text_audit = document.querySelector("#rel_int");

 function aumentarTextAudit() {
     if (text_audit.rows == "2") {
         text_audit.rows = "30"
     } else {
         text_audit.rows = "2"
     }
 }

 // mudar linhas da acoes 
 var text_acoes = document.querySelector("#acoes_int");

 function aumentarTextAcoes() {
     if (text_acoes.rows == "2") {
         text_acoes.rows = "30"
     } else {
         text_acoes.rows = "2"
     }
 }

 // mudar linhas dos exames enf 
 var text_exames_enf = document.querySelector("#exames_det");

 function aumentarTextExamesEnf() {
     if (text_exames_enf.rows == "2") {
         text_exames_enf.rows = "30"
     } else {
         text_exames_enf.rows = "2"
     }
 }

 // mudar linhas dos programacao_enf enf 
 var programacao_int = document.querySelector("#programacao_int");

 function aumentarTextProgInt() {
     if (programacao_int.rows == "2") {
         programacao_int.rows = "30"
     } else {
         programacao_int.rows = "2"
     }
 }

 // mudar linhas dos exames enf 
 var oportunidades_enf = document.querySelector("#oportunidades_det");

 function aumentarTextOportEnf() {
     if (oportunidades_enf.rows == "2") {
         oportunidades_enf.rows = "30"
     } else {
         oportunidades_enf.rows = "2"
     }
 }

 // aparecer campos relatorio detalhado
 $(document).ready(function() {
    if (document.querySelector("#relatorio-detalhado").value === 's') {
        $('#div-detalhado').css('display', 'grid');
        $('#text-detalhado').hide();
    }else{
        $('#div-detalhado').hide();
    }
     $('#relatorio-detalhado').change(function() {
         if ($(this).val() === 's') {
             $('#div-detalhado').css('display', 'grid');
             $('#text-detalhado').hide();
             $("#select_detalhes").val("s");


         } else {
             $('#div-detalhado').hide();
             $('#text-detalhado').show();

         }
     });
 });

 // aparecer campo atb em uso
 $(document).ready(function() {
     $('#atb').hide(); // Oculta o campo de texto quando a página carrega

     $('#atb_det').change(function() {
         if ($(this).val() === 's') {
             $('#atb').show();
         } else {
             $('#atb').hide();
         }
     });
 });
 // aparecer campo litros de O2
 $(document).ready(function() {
     $('#div-oxig').hide(); // Oculta o campo de texto quando a página carrega

     $('#oxig_det').change(function() {
         if ($(this).val() === 'Cateter' || $(this).val() == 'Mascara') {
             $('#div-oxig').show();
         } else {
             $('#div-oxig').hide();
         }
     });
 });
